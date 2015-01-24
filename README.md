Segment.io for FuelPHP
======================

Segment.io is a powerful platform to integrate all of your analytics into one easy to use API. You can attach everything from GTM and Google Universal Analytics to Zendesk and Uservoice, and more to your Segment.io account and start pumping data to everything all at once. It's like Bit API Hub for analytics.

Segment.io for FuelPHP helps to simplify the client/server side libraries for PHP developers by providing a unified PHP experience. You can call server side and client (browser) side functions based on what you're trying to accomplish, without having to integrate Analytics.js separately. As the Analytics.js library is a requirement for this package, you can still make calls to Analytics.js in your UX for things like click events, or other needs that will never be accessible server side.

See Segment.io's documentation for help on getting started with the [best practices](https://segment.com/help/best-practices/) for their analytics system.

Segment.io for FuelPHP provides the following advantages for your company.
* Your programmers have less to write.
* Provides a unified client/server package
* "Write it once" methodology (See "[Usage](#usage)" below)
* Bit API Hub uses this same package to track our own operations.
* See the "[Notions of Note](#notions-of-note)" section to see what idiosyncrasies we take care of for you that may not be readily apparent when working with Segment.io.
* Segment.io for FuelPHP is an analytics.js first package, as analytics.js is more flexible and therefore, generally more accurate.

Prerequisites
-------------

* PHP 5.3.3+
* FuelPHP 1.7.2 (May work with other versions, but only version 1.7.2 is supported at this time.)
* A [Segment.io account](https://segment.com)

Installation and Configuration
------------------------------

1. Download the sources to your fuel/packages directory. (fuel/packages/segment)
2. Install the Analytics.js code as described in the [Segment.io documentation](https://segment.com/docs/libraries/analytics.js/quickstart/). Add the "flush" token to the analytics.methods list in the snippet. If you intend to use debug mode, be sure to add the "debug" token to the list as well.
3. Edit your composer.json file in your FuelPHP root directory and add the following line to your "require" section.
``` "segmentio/analytics-php": "master" ```
4. Copy the segment/config/segment.php configuration file to your APPPATH/config directory and edit your settings there.
5. Edit your config.php and add "segment" to the list of packages to autoload, or use \Package::load('segment'); to load it in real time.
6. Create a template variable for the $analytics->render() method described below. Make sure the variable in your template appears **after** your Analytics.js code snippet.

Usage
-----

To run any function, write the following.

``` $analytics = \Segment\Analytics::instance(); ```

### Set User ID

The anonymous ID is automatically generated when you create the \Segment\Analytics object above, and stored in a session variable. (Part of segment.identity) You have two ways to set the userId parameter so that you'll never have to set it again. You can set it by calling the identify() method, or directly with the set_user_id() method that it relies on when it hasn't been set for that user.

**$user_id** - Set the identifier you wish to pass for any methods that rely on the userId parameter.

``` $analytics->set_user_id($user_id); ```

### Page

Segment.io requires at least one page view per page. The Segment code already includes an analytics.page() call which you **must remove** so you don't waste calls to Segment.io. Segment.io for FuelPHP will automatically generate the code for a raw page view only if you do not name your pages by calling the $analytics->page() method.

Please note that due to Segment.io's limitations, you will always have a page view sent through Analytics.js, even if you send one through PHP. If you wish to only create page views through PHP, do not run the render() method. If you need both, pass boolean false as the second parameter for the render() method to disable the JS page view, leaving only the PHP page view.

To aid in making calls to Mix Panel reliably, the JS output automatically calls analytics.flush() after analytics.alias().

**$page_data** - The array of data as specified for the [PHP implementation](https://segment.com/docs/libraries/php/#page). (The JS version will pick out the proper attributes from this array to send through the Analytics.js version.)  
**$js** - Set this to true to create the JS code for the specified parameters. The resulting code will be queued and ready for the render() method. You may only make one call for page() through this package, so further page() calls will replace the previous code. Directly add the analytics.page() calls in your template files for virtual page views. Set it to false to process it server-side.  
**$js_options** - Analytics.js allows you to pass options as a parameter. The docs specifically state that the "integrations" object could be an option, so by default, if $page_data contains said object, then it will appear in the options parameter. You may add extra "integrations" attributes, override them, or add different objects or parameters to the options list by setting this to an array.  
**$js_callback** - If you'd like to use a JS callback function, specify the exact raw JS code to use for that parameter.  

``` $analytics->page($page_data = array(), $js = true, $js_options = array(), $js_callback	= null); ```

### Alias

Alias allows you to track anonymous visitors to their new in-system identity. Please note that Mix Panel has a race condition where your events must race against the Mix Panel queue. [Learn more about this issue](https://segment.com/docs/integrations/mixpanel/#alias) and why it's best to alias your customers on the client side. For the same reason, Mix Panel requires you to alias on the client side **before** you alias on the server side if you decide to alias on the server side.

As it's best to alias your customers on the client side, you'll need to send your in-system identifier to the browser. That could produce a security flaw, especially if you're using a common name for an ID column in your database. For that reason, consider creating a new column in your database exclusively for analytics tracking. Name the new column something a hacker probably won't guess.

**$alias** - The array of data as specified for the [PHP implementation](https://segment.com/docs/libraries/php/#alias). (The JS version will pick out the proper attributes from this array to send through the Analytics.js version.) You may omit the previousId to use the package generated anonymousId set in the user's session. If you've called the identify() or set_user_id() methods, then you may omit the userId field for both JS and PHP as well. When you set a userId through alias(), the set_user_id() method is also called so that your script stays synchronized.  
**$js** - Set this to true to create the JS code for the specified parameters. The resulting code will be queued and ready for the render() method. You may only make one call for alias() through this package, so further alias() calls will replace the previous code. Set it to false to process it server-side.  
**$js_options** - Analytics.js allows you to pass options as a parameter. The docs specifically state that the "integrations" object could be an option, so by default, if $alias contains said object, then it will appear in the options parameter. You may add extra "integrations" attributes, override them, or add different objects or parameters to the options list by setting this to an array.  
**$js_callback** - If you'd like to use a JS callback function, specify the exact raw JS code to use for that parameter.  

``` $analytics->alias($alias = array(), $js = true, $js_options = array(), $js_callback = null); ```

### Identify

[Identify your customers](https://segment.com/docs/api/tracking/identify/) through PHP or JS.

**$identification** - The array of data as specified for the [PHP implementation](https://segment.com/docs/libraries/php/#identify). (The JS version will pick out the proper attributes from this array to send through the Analytics.js version.)  
**$js** - Set this to true to create the JS code for the specified parameters. The resulting code will be queued and ready for the render() method. You may only make one call for identify(), so further identify() calls will replace the previous code. Set it to false to process the call server-side.  
**$js_options** - Analytics.js allows you to pass options as a parameter. The docs specifically state that the "integrations" object could be an option, so by default, if $identification contains said object, then it will appear in the options parameter. You may add extra "integrations" attributes, override them, or add different objects or parameters to the options list by setting this to an array.  
**$js_callback** - If you'd like to use a JS callback function, specify the exact raw JS code to use for that parameter.  

``` $analytics->identify($identification = array(), $js = true, $render_safe = false, $js_options = array(), $js_callback = null); ```

### Group

The group() method allows you to connect people with companies, projects, or other group structures, however you define them.

**$group** - The array of data as specified for the [PHP implementation](https://segment.com/docs/libraries/php/#group). (The JS version will pick out the proper attributes from this array to send through the Analytics.js version.)  
**$js** - Set this to true to create the JS code for the specified parameters. The resulting code will be queued and ready for the render() method. You may only make one call for group() through this package, so further group() calls will replace the previous code. Set it to false to process it server-side.  
**$js_options** - Analytics.js allows you to pass options as a parameter. The docs specifically state that the "integrations" object could be an option, so by default, if $group contains said object, then it will appear in the options parameter. You may add extra "integrations" attributes, override them, or add different objects or parameters to the options list by setting this parameter to an array.  
**$js_callback** - If you'd like to use a JS callback function, specify the exact raw JS code to use for that parameter.  

``` $analytics->group($group = array(), $js = true, $js_options = array(), $js_callback = null); ```

### Track

Track every move your customers make. O.O Just be sure to let them know that you're doing so in your privacy policy.

**$track** - The array of data as specified for the [PHP implementation](https://segment.com/docs/libraries/php/#track). (The JS version will pick out the proper attributes from this array to send through the Analytics.js version.)  
**$js** - Set this to true to create the JS code for the specified parameters. The resulting code will be queued and ready for the render() method. You may may make multiple calls to track(), and each one will be added to the queue in the order you set them. Set this parameter to false to process it server-side.  
**$js_options** - Analytics.js allows you to pass options as a parameter. The docs specifically state that the "integrations" object could be an option, so by default, if $track contains said object, then it will appear in the options parameter. You may add extra "integrations" attributes, override them, or add different objects or parameters to the options list by setting this parameter to an array.  
**$js_callback** - If you'd like to use a JS callback function, specify the exact raw JS code to use for that parameter.  
**$noninteraction** - An interaction with Google Analytics signifies that the user has triggered an event. As we're generating code to load with the page, we're generating non-interaction code, so by default this is set to not be an interaction. Change it to false to send the call as an interaction hit.  

``` $analytics->track($track, $js = true, $js_options = array(), $js_callback = null, $noninteraction = true); ```

### Custom Functions

You can add custom calls to analytics.js. Use this code to set as many raw JS functions as you'd like.

**$custom_js_function** - Set your JS string here. Technically you can set as many functions as you'd like to in one go as this variable is just an arbitrary string. Call the custom() method multiple times to enqueue more JS functions. 

``` $analytics->custom($custom_js_function); ```

### Render Your JS

If you've queued JS calls for Analytics.js, you'll need to run the following code to render the output. Each code, with the exception of "Track" may only have one call made. The render function will produce the JS code for every function you've added to the queue, in the following order: page, alias, identify, group, track. If this order does not work for you, specify the order with the parameter shown below, as an array containing the aforementioned list in the proper order. The example shows the default.

The parameter is **optional** and it shows the default display order.
``` $analytics->render($order = array('page', 'alias', 'identify', 'group', 'track', 'custom')); ```



Notions of Note
---------------

1. Google Universal Analytics is natively supported, but the outdated Classic Analytics is not. (Though, Classic Analytics may still work for you) If the _ga cookie is present, the [special considerations](https://segment.com/docs/integrations/google-analytics/) for UA will be turned on automatically, such as setting nonInteraction to 1, and passing the _ga cookie to Segment.io on the server side. The _ga cookie only exists once the customer has viewed the page for the first time, PHP will not have access to the cookie for the first page that your customer views.
2. All server side requests support the "context" object to deliver data, such as User Agent, to Segment.io. The [special context](https://segment.com/docs/api/tracking/track/#special-context) values that you'll need to pass manually if you want them, are "app," "device," "location," "network," "screen," "traits," and the undocumented "referrer" object.
3. When you make a call server side, it's sent to Segment.io immediately. When you make a call in the client size, it queues the JS functions to be sent to the browser when you call the render() method.
4. Segment.io for FuelPHP generates an anonymousId and passes it to analytics.js automatically to keep both server and client side scripts synchronized.

Troubleshooting
---------------

When your settings aren't taking effect or files/classes can't be found, clear your FuelPHP cache. (APPPATH/cache)

Credits
-------

This package makes reference to third-party code written by Segment.io. (See "Installation and Configuration" for the "require" directive to download their SDK code.) Segment.io licenses their PHP SDK under the MIT license. Bit API Hub is not affiliated with Segment.io in any way, save for that we're a customer of theirs.

The package "Segment.io for FuelPHP" is licensed by Bit API Hub under the [Apache 2.0 license](LICENSE), a copy of which is provided in this repository.