( function( window, document ) {
	'use strict';

	var settings = window.tagdiv_liveblog_realtime;
	if ( ! settings || ! settings.post_id || ! settings.client_url ) {
		return;
	}

	var runtime = {
		store: null,
		ioReady: false,
		started: false,
		socket: null,
		pollingSuspended: false,
		reconcileInFlight: false,
		reconcileAgain: false,
		reconcileTimer: null
	};

	var composeGlobal = '__REDUX_DEVTOOLS_EXTENSION_COMPOSE__';
	var hadComposeGlobal = Object.prototype.hasOwnProperty.call( window, composeGlobal );
	var previousCompose = window[ composeGlobal ];
	var composeRestored = false;

	function restoreComposeGlobal() {
		if ( composeRestored ) {
			return;
		}

		composeRestored = true;
		if ( hadComposeGlobal ) {
			window[ composeGlobal ] = previousCompose;
		} else {
			try {
				delete window[ composeGlobal ];
			} catch ( error ) {
				window[ composeGlobal ] = undefined;
			}
		}
	}

	function composeEnhancers( enhancers ) {
		if ( ! enhancers.length ) {
			return function( createStore ) {
				return createStore;
			};
		}

		return function( createStore ) {
			return enhancers.reduceRight( function( nextCreateStore, enhancer ) {
				return enhancer( nextCreateStore );
			}, createStore );
		};
	}

	function captureStoreEnhancer( baseEnhancer ) {
		return function( createStore ) {
			var enhancedCreateStore = baseEnhancer ? baseEnhancer( createStore ) : createStore;

			return function() {
				var store = enhancedCreateStore.apply( this, arguments );
				if ( store && typeof store.dispatch === 'function' && typeof store.getState === 'function' ) {
					runtime.store = store;
					maybeStart();
				}
				return store;
			};
		};
	}

	// Automattic Liveblog 1.12.x builds its Redux store with composeWithDevTools.
	// Intercept exactly that enhancer call before the upstream app bundle loads,
	// preserve any real Redux DevTools enhancer, and capture the fully enhanced
	// store without modifying the upstream bundle.
	window[ composeGlobal ] = function() {
		var args = Array.prototype.slice.call( arguments );
		var baseEnhancer;

		restoreComposeGlobal();

		if ( typeof previousCompose === 'function' ) {
			baseEnhancer = previousCompose.apply( window, args );
		} else if ( args.length === 1 && typeof args[ 0 ] === 'object' ) {
			// The options-currying form is not used by Liveblog, but preserve its
			// compose contract if another caller reaches the wrapper first.
			return function() {
				return captureStoreEnhancer( composeEnhancers( Array.prototype.slice.call( arguments ) ) );
			};
		} else {
			baseEnhancer = composeEnhancers( args );
		}

		return captureStoreEnhancer( baseEnhancer );
	};

	// If the upstream app never asks for the enhancer, do not leave a global
	// override behind. Native polling continues unaffected.
	window.setTimeout( restoreComposeGlobal, 10000 );

	function loadSocketClient() {
		if ( typeof window.io === 'function' ) {
			runtime.ioReady = true;
			maybeStart();
			return;
		}

		var script = document.createElement( 'script' );
		script.src = settings.client_url;
		script.async = true;
		script.setAttribute( 'data-tagdiv-liveblog-realtime-client', '1' );
		script.onload = function() {
			if ( typeof window.io === 'function' ) {
				runtime.ioReady = true;
				maybeStart();
			}
		};
		script.onerror = function() {
			runtime.ioReady = false;
		};
		( document.head || document.documentElement ).appendChild( script );
	}

	function maybeStart() {
		if ( runtime.started || ! runtime.store || ! runtime.ioReady ) {
			return;
		}

		var container = document.getElementById( 'wpcom-liveblog-container' );
		if ( container && container.querySelector( '.liveblog-feed' ) ) {
			window.setTimeout( startSocket, 0 );
			return;
		}

		if ( ! container || typeof window.MutationObserver !== 'function' ) {
			window.setTimeout( startSocket, 250 );
			return;
		}

		var observer = new window.MutationObserver( function() {
			if ( container.querySelector( '.liveblog-feed' ) ) {
				observer.disconnect();
				window.setTimeout( startSocket, 0 );
			}
		} );
		observer.observe( container, { childList: true, subtree: true } );

		window.setTimeout( function() {
			observer.disconnect();
			if ( ! runtime.started ) {
				startSocket();
			}
		}, 10000 );
	}

	function startSocket() {
		if ( runtime.started || ! runtime.store || typeof window.io !== 'function' ) {
			return;
		}

		runtime.started = true;
		runtime.socket = window.io( settings.url, {
			path: settings.path || '/socket.io/',
			transports: [ 'websocket' ],
			upgrade: false,
			reconnection: true,
			timeout: 5000,
			auth: {
				postId: String( settings.post_id )
			}
		} );

		runtime.socket.on( 'tagdiv-liveblog:ready', function( payload ) {
			if ( ! validPostPayload( payload ) ) {
				return;
			}
			reconcile();
		} );

		runtime.socket.on( 'tagdiv-liveblog:changed', function( payload ) {
			if ( ! validPostPayload( payload ) ) {
				return;
			}
			scheduleReconcile();
		} );

		runtime.socket.on( 'disconnect', function() {
			resumePolling();
		} );

		runtime.socket.on( 'connect_error', function() {
			resumePolling();
		} );
	}

	function validPostPayload( payload ) {
		return payload && Number( payload.postId ) === Number( settings.post_id );
	}

	function scheduleReconcile() {
		if ( runtime.reconcileTimer ) {
			return;
		}

		runtime.reconcileTimer = window.setTimeout( function() {
			runtime.reconcileTimer = null;
			reconcile();
		}, 50 );
	}

	function reconcile() {
		if ( ! runtime.store ) {
			return;
		}

		if ( runtime.reconcileInFlight ) {
			runtime.reconcileAgain = true;
			return;
		}

		runtime.reconcileInFlight = true;

		var state = runtime.store.getState();
		var newest = getNewestKnownEntry( state );
		var start = Math.max( 0, Number( newest.timestamp || 0 ) - 1 );
		var end = Math.floor( Date.now() / 1000 ) + 2;
		var endpoint = String( window.liveblog_settings && window.liveblog_settings.endpoint_url || '' );

		if ( ! endpoint ) {
			finishReconcile();
			return;
		}

		endpoint = endpoint.replace( /\/?$/, '/' );
		var separator = endpoint.indexOf( '?' ) === -1 ? '?' : '&';
		var url = endpoint + 'entries/' + start + '/' + end + '/' + separator +
			'tagdiv-liveblog-realtime=' + Date.now();
		var controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
		var timeout = window.setTimeout( function() {
			if ( controller ) {
				controller.abort();
			}
		}, Number( settings.reconcile_timeout ) || 8000 );

		window.fetch( url, {
			method: 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: {
				'Accept': 'application/json',
				'Cache-Control': 'no-cache'
			},
			signal: controller ? controller.signal : undefined
		} ).then( function( response ) {
			if ( ! response.ok ) {
				throw new Error( 'Liveblog reconciliation failed with HTTP ' + response.status );
			}
			return response.json();
		} ).then( function( payload ) {
			if ( ! payload || ! Array.isArray( payload.entries ) ) {
				throw new Error( 'Liveblog reconciliation returned an invalid payload.' );
			}

			var currentState = runtime.store.getState();
			runtime.store.dispatch( {
				type: 'POLLING_SUCCESS',
				payload: payload,
				renderNewEntries: shouldRenderNewEntries( currentState )
			} );

			// The upstream updatePollingInterval middleware may restart polling when
			// a response changes refresh_interval. Always cancel after the
			// authoritative response has passed through all native middleware and
			// reducers, including on later reconciliations.
			if ( runtime.socket && runtime.socket.connected ) {
				suspendPolling();
			}
			finishReconcile();
		} ).catch( function() {
			resumePolling();
			finishReconcile();
		} ).then( function() {
			window.clearTimeout( timeout );
		} );
	}

	function finishReconcile() {
		runtime.reconcileInFlight = false;
		if ( runtime.reconcileAgain ) {
			runtime.reconcileAgain = false;
			scheduleReconcile();
		}
	}

	function getNewestKnownEntry( state ) {
		if ( state && state.polling && state.polling.newestEntry ) {
			return state.polling.newestEntry;
		}
		if ( state && state.api && state.api.newestEntry ) {
			return state.api.newestEntry;
		}
		return {
			id: window.liveblog_settings && window.liveblog_settings.latest_entry_id || 0,
			timestamp: window.liveblog_settings && window.liveblog_settings.latest_entry_timestamp || 0
		};
	}

	// Keep this logic equivalent in behavior to Automattic Liveblog 1.12.x
	// shouldRenderNewEntries(). The resulting POLLING_SUCCESS action then flows
	// through the upstream reducers unchanged.
	function shouldRenderNewEntries( state ) {
		if ( ! state || ! state.pagination || Number( state.pagination.page ) !== 1 ) {
			return false;
		}

		if ( state.polling && state.polling.entries && Object.keys( state.polling.entries ).length > 0 ) {
			return false;
		}

		var entries = state.api && state.api.entries ? state.api.entries : {};
		var firstKey = Object.keys( entries )[ 0 ];
		var element = firstKey ? document.getElementById( firstKey ) : null;

		if ( ! element ) {
			return true;
		}

		return element.getBoundingClientRect().y > 0;
	}

	function suspendPolling() {
		if ( ! runtime.store ) {
			return;
		}

		// This is intentionally idempotent at the Redux level. Re-dispatching
		// CANCEL_POLLING also cancels any polling loop that upstream middleware
		// may have restarted while processing the reconciliation response.
		runtime.store.dispatch( { type: 'CANCEL_POLLING' } );
		runtime.pollingSuspended = true;
	}

	function resumePolling() {
		if ( ! runtime.pollingSuspended || ! runtime.store ) {
			return;
		}

		runtime.store.dispatch( { type: 'START_POLLING' } );
		runtime.pollingSuspended = false;
	}

	loadSocketClient();
} )( window, document );
