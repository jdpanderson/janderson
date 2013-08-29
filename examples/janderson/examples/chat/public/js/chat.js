if (typeof janderson === "undefined") janderson = {};
if (typeof janderson.examples === "undefined") janderson.examples = {};

/** 
 * Chat interface controller.
 */
janderson.examples.Chat = function() {
	this.client = new janderson.examples.ChatClient();
	janderson.examples.chat.attachEventListeners(this);
	janderson.examples.chat.showRegistration();
};
janderson.examples.Chat.prototype = {
	id: null,
	client: null,
	room: null,
	index: 0,

	/**
	 * Determines if a websocket (a live message communication channel) is currently active.
	 */
	socketActive: function() {
		return false;
	},

	poll: function() {
		/* Only poll if we don't have a websocket or if we're in a room. */
		if (!this.socketActive() && this.room) {
			this.getMessages();
		}

		/* Continue polling... */
		var scope = this;
		window.setTimeout(function() { scope.poll(); }, 1000);
	},

	register: function(nickname, password) {
		this.client.register(nickname, password, { success: this.onRegister, failure: this.onRegisterFailure, scope: this });
	},

	getRooms: function() {
		this.client.getRooms(this.id, { success: this.onRoomList, failure: this.onRoomListFailure, scope: this });
	},

	createRoom: function(room) {
		this.client.createRoom(this.id, room, room, { success: this.onCreateRoom, failure: this.onCreateRoomFailure, scope: this });
	},

	join: function(room) {
		this.client.join(this.id, room, { success: this.onJoin, failure: this.onJoinFailure, scope: this });
	},

	leave: function() {
		this.client.leave(this.id, this.room, { success: this.onLeave, failure: this.onLeaveFailure, scope: this });
	},

	message: function(message) {
		if (!this.room) {
			janderson.examples.chat.addMessage("Not currently in a channel.");
		} else {
			this.client.message(this.id, this.room, message, { success: this.onMessage, failure: this.onMessageFailure, scope: this });
		}
	},

	/**
	 * Make sure no more than 1 getMessages request is in flight at once, to prevent wierd side-effects. 
	 */
	getMessagesPending: false,

	getMessages: function() {
		if (this.getMessagesPending) {
			return;
		}

		if (!this.room) {
			return;
		}

		this.getMessagesPending = true;
		this.client.messages(this.id, this.room, this.index, { success: this.onMessages, failure: this.onMessagesFailure, scope: this });
	},

	onRoomChange: function(room, index) {
		this.index = index || 0;
		this.room = room;
		janderson.examples.chat.setActiveRoom(this.room);
		if (room) {
			janderson.examples.chat.addMessage("Now chatting in " + room);
			janderson.examples.chat.scrollDown();
		}
	},

	/* Callbacks */
	onRegister: function(result) {
		this.id = result;
		this.getRooms();
		this.poll();
		janderson.examples.chat.hideRegistration();
	},
	onRoomList: function(result) {
		var lobbyExists = false;

		for (var i = 0; i < result.length; i++) {
			janderson.examples.chat.addRoom(result[i], this);
			if (result[i] == 'Lobby') {
				lobbyExists = true;
			}
		}

		if (!this.room) {
			if (lobbyExists) {
				this.join('Lobby');
			} else {
				this.createRoom('Lobby');
			}
		}
	},
	onCreateRoom: function(result) {
		janderson.examples.chat.addRoom(result, this);
		this.onRoomChange(result);
	},
	onMessage: function(result) {
		this.getMessages();
	},
	onMessages: function(result) {
		this.getMessagesPending = false;
		this.index += result.length;

		for (var i = 0; i < result.length; i++) {
			janderson.examples.chat.addMessage(result[i][2], result[i][1], new Date(result[i][0] * 1000));
		}

		if (result.length) {
			janderson.examples.chat.scrollDown();
		}
	},
	onJoin: function(result, params, request) {
		this.onRoomChange(request.params[0].room, result);
	},
	onLeave: function(result) {
		this.onRoomChange(null);
	},
	onCreateRoomFailure: function(result) { /* Nothing for now */ },
	onMessageFailure: function(result) { /* Nothing for now */ },
	onMessagesFailure: function(result) { this.getMessagesPending = false; },
	onRegisterFailure: function(result) { janderson.examples.chat.registrationError(result.message || "Unknown Error"); },
	onRoomListFailure: function(result) { /* Nothing for now. */ },
	onJoinFailure: function(result) { /* Nothing for now. */ },
	onLeaveFailure: function(result) { /* Nothing for now. */ }
};

/**
 * Interface to DOM (view) manipulation.
 */
janderson.examples.chat = {
	/**
	 * Bind DOM events to the chat object.
	 */
	attachEventListeners: function(chat) {
		/* Modal register dialog submit triggers a register call. */
		$('#register-form').on('submit', function() { chat.register($('#register-dialog-nickname').val(), $('#register-dialog-password').val()); return false; });

		/* Create button shows a modal dialog. */
		$('#channels-actions-create').on("click", function() { $('#channel-create-dialog').modal(); });

		/* Modal submit creates a channel with a given name */
		$('#channel-create-form').on("submit", function() { chat.createRoom($('#channel-create-dialog-name').val()); $('#channel-create-dialog').modal('hide'); return false; });

		/* Refresh button refreshes the channel list. */
		$('#channels-actions-refresh').on("click", function() { chat.getRooms(); });

		/* Send button and enter in the text box send a message. */
		var sendFn = function() { var txt = $('#channel-textarea'); if (txt.val() != "") { chat.message(txt.val()); txt.val(''); }};
		$('#channel-textarea').keypress(function(e) { if (e.which == 13 && !e.shiftKey) { sendFn(); e.preventDefault(); }});
		$('#channel-send').click(sendFn);
	},
	addRoom: function(room, chat) {
		var old = $('#channels-list li').filter(function() { return $.data(this, "room") == room; });
		if (old.length) {
			return;
		}

		var label = $(document.createElement('a')).append($(document.createTextNode(room))).on("click", function() { chat.join(room) });
		var container = $(document.createElement('li')).data("room", room).append(label);

		$('#channels-list ul').append(container);
	},
	showRegistration: function() {
		$('#register-dialog').modal();
	},
	hideRegistration: function() {
		$('#register-dialog').modal('hide');
	},
	registrationError: function(error) {
		$('#register-dialog .form-group').addClass('has-error');
		$('#register-dialog-message').text(error);
	},
	removeRoom: function(room) {
		$('#channels-list .room').filter(function() { return $.data(this, "room") == room; }).remove();
	},
	setActiveRoom: function(room) {
		$('#channels-list ul li').each(function(i, item) { if ($(item).data("room") != room) { $(item).removeClass('active'); } else { $(item).addClass('active'); } });
	},
	addMessage: function(message, user, date) {
		var container = $(document.createElement('div'))
			.addClass('message');
		var date = $(document.createElement('span'))
			.addClass('date')
			.append($(document.createTextNode(date ? ("[" + date.toLocaleString() + "] ") : "")));
		var user = $(document.createElement('span'))
			.addClass('user')
			.append($(document.createTextNode(user ? (user + ": ") : "")));
		var message = $(document.createElement('span'))
			.addClass('text')
			.append($(document.createTextNode(message)));

		container.append(date).append(user).append(message);
		
		$('#channel-main').append(container);
	},
	scrolling: false,
	scrollDown: function() {
		if (janderson.examples.chat.scrolling) {
			return;
		}

		janderson.examples.chat.scrolling = true;
		var el = $('#channel-main');
		el.animate({ scrollTop: el[0].scrollHeight }, 1000, "linear", function() {
			janderson.examples.chat.scrolling = false;
		});
	}
};

/**
 * A wrapper around the JSONRPCClient providing something that looks like a more attractive API.
 */
janderson.examples.ChatClient = function() {
	this.client = new janderson.examples.JSONRPCClient("/jsonrpc/chat");
};
janderson.examples.ChatClient.prototype = {
	register: function(handle, secret, options) {
		this.client.call('register', [{ 'handle': handle, 'secret': secret }], options);
	},
	unregister: function(id, options) {
		this.client.call('unregister', [{ 'id': id }], options);
	},
	getRooms: function(id, options) {
		this.client.call('getRooms', [{ 'id': id }], options);
	},
	getUsers: function(id, room, options) {
		this.client.call('getUsers', [{ 'id': id, 'room': room }], options);
	},
	createRoom: function(id, room, desc, options) {
		this.client.call('createRoom', [{ 'id': id, 'room': room, 'data': desc }], options);
	},
	join: function(id, room, options) {
		this.client.call('join', [{ 'id': id, 'room': room }], options);
	},
	leave: function(id, room, options) {
		this.client.call('leave', [{ 'id': id, 'room': room }], options);
	},
	message: function(id, room, message, options) {
		this.client.call('message', [{ 'id': id, 'room': room, 'message': message }], options);
	},
	messages: function(id, room, index, options) {
		this.client.call('messages', [{ 'id': id, 'room': room, 'index': index }], options);
	}
};

janderson.examples.JSONRPCClient = function(endpointURL) {
	this.url = endpointURL;
};
janderson.examples.JSONRPCClient.serial = 0;
janderson.examples.JSONRPCClient.prototype = {
	/**
	 * Get an identifer unique to this request on this page.
	 *
	 * @return int 
	 */
	getSerial: function() {
		return ++janderson.examples.JSONRPCClient.serial;
	},

	/**
	 * Call a JSON-RPC 2.0 method, with an array of parameters, and a set of options.
	 *
	 * @param string method
	 * @param mixed[] params
	 * @pram Object options
	 */
	call: function(method, params, options) {
		var request = {
			jsonrpc: "2.0",
			method: method,
			params: params || [],
			id: this.getSerial()
		};
		var scope = this;
		var xhr = new XMLHttpRequest();
		xhr.open('POST', this.url, true); /* Username/Password args not currently used. */
		xhr.setRequestHeader('Content-Type', 'application/json');
		xhr.onreadystatechange = function() { scope.onReadyStateChange(xhr, request, options || {}); };
		xhr.send(JSON.stringify(request));
	},

	/**
	 * The onreadystatechange callback
	 *
	 * @param XMLHttpRequest xhr
	 * @param Object request
	 * @param Object options
	 * @private
	 */
	onReadyStateChange: function(xhr, request, options) {
		if (xhr.readyState === 4) {
			var scope = options.scope || window;
			if (xhr.status == 200) {
				var response = this.decodeResponse(xhr.responseText);
				var hasResult = (response.result != undefined && response.result != null);
				if (!response.error && hasResult && typeof options.success === "function") {
					options.success.call(scope, response.result, options.params, request, xhr);
				} else if ((response.error || !hasResult) && typeof options.failure === "function") {
					options.failure.call(scope, response.error, options.params, request, xhr);
				}
			} else {
				if (typeof options.failure === "function") {
					options.failure.apply(scope, null, options.params, request, xhr);
				}
			}
		}
	},

	/**
	 * Decode the response text.
	 *
	 * @param string responseText
	 * @private
	 */
	decodeResponse: function(responseText) {
		try {
			return JSON.parse(responseText);
		} catch (e) {
			return {
				jsonrpc: "2.0",
				error: {
					code: -32700,
					message: e.toString()
				},
				id: null
			};
		}
	},

	/**
	 * Log a message to the console, if the console is available.
	 *
	 * @param string message
	 */
	log: function(message) {
		if (console) {
			console.log(message);
		}
	}
};