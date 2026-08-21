<?php
return [
	'buttons' => [
		'submit' => 'Отправить',
		'cancel' => 'Отмена',
	],
	'errors' => [
		'404' => 'Упс! Страница не найдена.',
	],

	'search' => 'Search',
	'tags' => 'Tags',
	'home' => 'Home',
	'profile' => 'Profile',
	'articles' => 'articles',
	'login' => 'Sign in',
	'logout' => 'Sign out',
	'register' => 'Register',
	'invitations' => 'Invitations',
	'request_invitation' => 'Request invitation',
	'settings' => 'Settings',
	'admin_panel' => 'Admin panel',
	'messages' => 'Messages',
	'share' => 'Share',

	'moderation_log' => 'Moderation Log',
	'notes' => 'Notes',
	'activity' => 'Activity',
	'domains' => 'Domains',
	'ban' => 'Ban',

	'about' => 'About',
	'statistics' => 'Statistics',
	'filters' => 'Filters',

	'toggle_theme' => 'Toggle theme',
	'notifications' => 'Notifications',
	'bookmarks' => 'Bookmarks',
	'muted' => 'Muted',
	'suggestions' => 'Suggestions',
	'comments' => 'Comments',

	// EMAIL TEMPLATE ELEMENTS
	'email_activation_subject' => '🚀 Activate your account on %s',
	'email_activation_body'    => '<h3>Welcome to the community, %s!</h3>
                                    <p>You have successfully registered on the platform.</p>
                                    <p>To activate your account and gain the ability to submit posts and leave comments, please click the following link:</p>
                                    <p><a href="%s"><strong>Confirm registration and activate account</strong></a></p>
                                    <br>
                                    <p><em>If you did not register on our website, simply ignore this email.</em></p>',

	'email_recovery_subject'   => '🔒 Password Recovery on %s',
	'email_recovery_body'      => '<h3>Access Recovery Request</h3>
                                    <p>Hello, %s!</p>
                                    <p>We received a request to reset the password for your account.</p>
                                    <p>To create a new secure password, please follow this secure link:</p>
                                    <p><a href="%s"><strong>Create a new password and log in</strong></a></p>
                                    <br>
                                    <p><strong>Warning:</strong> This link is valid for 1 hour. If you did not request a password reset, delete this email immediately.</p>',


	// === INVITATION SYSTEM ===

	'email_invitation_subject' => '🎟️ You\'ve been invited to join %s',
	'email_invitation_body' => '<h2>You\'ve been invited to a private community!</h2>
								<p>Hello!</p>
								<p>A community member has invited you to register on <strong>%s</strong>.</p>
								<p>To register, please follow the link below:</p>
								<p><a href="%s"><strong>👉 Register via Invitation</strong></a></p>
								<br>
								<p><strong>⏰ Expiration:</strong> This link is valid until %s</p>
								<p><em>If you don\'t know who invited you or don\'t plan to register, simply ignore this email.</em></p>',

	'email_invitation_request_approved_subject' => '✅ Your invitation request has been approved — %s',
	'email_invitation_request_approved_body' => '<h2>Great news!</h2>
								<p>Hello!</p>
								<p>Your request to join <strong>%s</strong> has been approved.</p>
								<p>To register, please follow the link below:</p>
								<p><a href="%s"><strong>👉 Register Now</strong></a></p>
								<br>
								<p><strong>⏰ Expiration:</strong> This link is valid until %s</p>
								<p>Welcome to the community!</p>',

	'email_invitation_request_rejected_subject' => '❌ Your invitation request — %s',
	'email_invitation_request_rejected_body' => '<h2>Application Review Result</h2>
								<p>Hello!</p>
								<p>Unfortunately, your request to join <strong>%s</strong> was not approved.</p>
								<p>If you have any questions, please contact the administration.</p>',
];
