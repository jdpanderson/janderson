if (typeof janderson === "undefined") janderson = {};
if (typeof janderson.examples === "undefined") janderson.examples = {};

/** 
 * Chat interface controller.
 */
janderson.examples.Chat = function(handle, secret) {
	this.client = new janderson.examples.ChatClient();
	this.client.register(handle, secret, { success: this.onRegister, failure: this.onRegisterFailure, scope: this });
	janderson.examples.chat.attachEventListeners(this);
};
janderson.examples.Chat.prototype = {
	id: null,
	client: null,
	room: null,
	index: 0,

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

	getMessages: function() {
		if (!this.room) {
			return;
		}
		this.client.messages(this.id, this.room, this.index, { success: this.onMessages, failure: this.onMessagesFailure, scope: this });
	},

	onRoomChange: function(room) {
		this.index = 0;
		this.room = room;
		janderson.examples.chat.setActiveRoom(this.room);
	},

	/* Callbacks */
	onRegister: function(result) {
		this.id = result;
	},
	onRoomList: function(result) {
		for (var i = 0; i < result.length; i++) {
			janderson.examples.chat.addRoom(result[i][0]);
		}
	},
	onCreateRoom: function(result) {
		janderson.examples.chat.addRoom(result);
		this.onRoomChange(result);
	},
	onMessage: function(result) {
		console.debug("message sent");
		console.debug(result);
		this.getMessages();
	},
	onMessages: function(result) {
		console.debug("messages");
		console.debug(result);

		this.index += result.length;

		for (var i = 0; i < result.length; i++) {
			janderson.examples.chat.addMessage(result[i][2], result[i][1], new Date(result[i][0] * 1000));
		}

	},
	onJoin: function(result, params, request) {
		this.onRoomChange(request.params[0].room);
	},
	onLeave: function(result) {
		console.debug(result);
		janderson.example.chat.setActiveRoom(false);
	},
	onCreateRoomFailure: function(result) { /* Nothing for now */ },
	onMessageFailure: function(result) { /* Nothing for now */ },
	onRegisterFailure: function(result) { /* Nothing for now. */ },
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
		/* Create button shows a modal dialog. */
		$('#channels-actions-create').on("click", function() { $('#channel-create-dialog').modal(); });

		/* Modal submit creates a channel with a given name */
		$('#channel-create-dialog-submit').on("click", function() { chat.createRoom($('#channel-create-dialog-name').val()); $('#channel-create-dialog').modal('hide'); });

		/* Refresh button refreshes the channel list. */
		$('#channels-actions-refresh').on("click", function() { chat.getRooms(); });

		/* Send button and enter in the text box send a message. */
		var sendFn = function() { var txt = $('#channel-textarea'); if (txt.val() != "") { chat.message(txt.val()); txt.val(''); }};
		$('#channel-textarea').keypress(function(e) { if (e.which == 13 && !e.shiftKey) { sendFn(); e.preventDefault(); }});
		$('#channel-send').click(sendFn);
	},
	addRoom: function(room) {
		var old = $('#channels-list .room').filter(function() { return $.data(this, "room") == room; });
		if (old.length) {
			return;
		}

		var container = $(document.createElement('div'))
			.addClass('room')
			.data("room", room);
		var label = $(document.createElement('div'))
			.addClass('label')
			.addClass('label-primary')
			.append($(document.createTextNode(room)));

		container.append(label);

		$('#channels-list').append(container);
	},
	removeRoom: function(room) {
		$('#channels-list .room').filter(function() { return $.data(this, "room") == room; }).remove();
	},
	setActiveRoom: function(room) {
		$('#channels-list .room').each(function(i, item) { var label = $(item).find('.label'); if ($(item).data("room") != room) { $(label).removeClass('label-info'); } else { $(label).addClass('label-info'); } });
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
				if (!response.error && response.result && typeof options.success === "function") {
					options.success.call(scope, response.result, options.params, request, xhr);
				} else if ((response.error || !response.result) && typeof options.failure === "function") {
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