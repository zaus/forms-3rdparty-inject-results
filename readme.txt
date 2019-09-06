=== Forms: 3rd-Party Inject Results ===
Contributors: zaus
Donate link: http://drzaus.com/donate
Tags: contact form, form, CRM, mapping, 3rd-party service, services, remote request, forms-3rdparty, inject response, inject results
Requires at least: 3.0
Tested up to: 5.2.2
Stable tag: trunk
License: GPLv2 or later

Injects the response from a Forms: 3rdparty submission into the original contact form.

== Description ==

Allows you to include results from a [Forms 3rdparty Integration](http://wordpress.org/plugins/forms-3rdparty-integration/) submission by flattening the response and inserting it within the original contact form submission.

== Installation ==

1. Unzip, upload plugin folder to your plugins directory (`/wp-content/plugins/`)
2. Make sure [Forms 3rdparty Integration](http://wordpress.org/plugins/forms-3rdparty-integration/) is installed and settings have been saved at least once.
3. Activate plugin
4. In the newly available section "Inject Results" in the '3rdparty services' admin, enter the flattened service response keys like `Response/Body/SomeKey`, one per line.
	a. If the response is JSON or XML it will scan the elements/keys according to the segments you've entered; in the above example it will look for `{ Response: { Body: { SomeKey: "foobar" } } }` and include "foobar" with the submission.
5. Some contact form plugins only allow injecting/overwriting an existing field (e.g. Gravity Forms).  In these cases you can provide an "alias" to overwrite with `Response/Body/SomeKey=the_alias`, where _the_alias_ is the contact form field to override.
	a. With Gravity Forms, fields should be overwritten using aliases like `input_X` where 'X' is the field's id.


== Frequently Asked Questions ==

= How does it add the response values? =

If you have an endpoint [test-response.php](http://yoursite.com/plugins/forms-3rdparty-inject-results/test-response.php) that will "echo" back your 3rdparty submission with keys altered to be prefixed with 'req-', then if your submission was

	{ name: { first: "FirstName", last: "LastName" }, email: "myemail@email.com", etc: "foobar" }

The response would be flattened and prefixed to

	{ "req-name/first": "FirstName", "req-name/last": "LastName", "req-email": "myemail@email.com", "req-etc": "foobar" }

You would then inject `req-name/first` or `req-etc`.

= What are some XML/JSON examples? =

*XML*

	[env:Envelope/env:Body/ns1:Response/ns1:Resultstatus] => foo
	[env:Envelope/env:Body/ns1:Response/ns1:Result] => bar
	[env:Envelope/env:Body/ns1:Response/ns1:Description] => baz

*JSON*

	[Body/Response/ResultStatus] => foo
	[Body/Response/Result] => bar
	[Body/Response/Description] => baz

	
Note that XML responses will include the namespace prefixes.  You may then reference them by the entire key shown above.

= It doesn't work right... =

Drop an issue at https://github.com/zaus/forms-3rdparty-inject-results

== Screenshots ==

N/A.

== Changelog ==

= 0.3 =
* fix: inconsistent nested key delimiters, now expecting '/'
* removed testing endpoint per WP Security request (see archives for example)

= 0.2 =
* confirmed with GF at least

= 0.1 =

IT HAS BEGUN

== Upgrade Notice ==

= 0.3 =
Delimiter format handling has changed.  May be a breaking change if you were using it "wrong" (according to how it was previously described).