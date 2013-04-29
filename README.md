# TwOauth
## About
This is a simple php class that allows you to connect to Twitter's oAuth API. Currently this class only uses **HMAC-SHA1** encryption for building signatures as this will be enough for most use cases (I'll add the others later). This class currently does **not** implement **xAuth**.

## Installation
Download the folder and copy it into your application. Then simply _require_ or _include_ the class.

	require('path/to/TwOauth.php');

Please make sure that the TwOauthKeys.json file is also available in the same directory.

Change the keys and details in the **TwOauthKeys.json** file.

	{
	    "consumer_key": "",
	    "consumer_secret": "",
	    "oauth_callback": "",
	    "session_oauth_token_name": "oauth_token",
	    "session_oauth_token_secret_name": "oauth_token_secret"
	}
	
The **consumer_key** and **consumer_secret** as provided by Twitter when creating a new app. The **oauth_callback** is the page where Twitter should redirect to once authenticated. This can be left blank if you are not using a redirect. **session_oauth_token_name** and **session_oauth_token_secret_name** are the names of the session variables that will store the consumer oAuth keys. These are not required if you don't intend on storing them in the $_SESSION variable. In which case, you can pass them into the __constuct mechanism when instantiating an object.

## Usage
To use the library, simply instantiate a new TwOuth object:

	$auth = new TwOuth();
	// Or if you want to pass in the oauth_token or 
	// oauth_token_secret
	$auth = new TwOuth('oauth_token', 'oauth_token_secret');

You can pass in the **oauth_token** and **oauth_token_secret** to the constructor after you have them or, if you don't want to use $_SESSION variables. I would suggest using the $_SESSION variables for this.

### Requesting a token
To request a token simply call:

	$url = $auth->getRequestToken();
	
This will return **oauth_token**, **oauth_token_secret**.  At this point you probably want to save these keys in `$_SESSION['oauth_token']` and `$_SESSION['oauth_token_secret']`. Or you can save them else where, but they will be needed when the the user is redirected back to the callback page. I recommend using sessions.

Now we have to concatenate the **oauth_token** to the authenticate url:

	<?php $url = 'oauth_token='.$_SESSION['oauth_token]; ?>
	<a href="https://api.twitter.com/oauth/authenticate?<?= $url; ?>">Sign in with Twitter</a>
	
### Access Keys
The **oauth_token** and **oauth_verifire** returned to the callback from Twitter will be required for this step. Either save the oauth_token to the `$_SESSION['oauth_token']` or pass it through to the object when instantiating it. You should also check that the oauth_token that is returned is the same as the one that you saved on the previous step.

	$auth = new TwOauth();
	$keys = $auth->getAccessKey('oauth_verifier');
	
	// Or if you're not using session for this
	$auth = new TwOauth(
		'oauth_token',
		'oauth_token_secret'
	);
	$keys = $auth->getAccessKey('oauth_verifier');
	
The upgraded **oauth_token** and **oauth_token_secret** will be returned and this should be stored by the app. At this point you probably want to save these keys in `$_SESSION['oauth_token']` and `$_SESSION['oauth_token_secret']`. As these will be needed for every signed request to Twitter.

If at this point you have the key and want to continue making a request using the same instance of TwOauth, then you'll have to update the keys by:

	$auth->setTokens($oauth_token, $oauth_token_secret);
	
This will only be required if you are using the same instance. I.e. if you create a `new TwOauth` later on, these keys will be set form the $_SESSION variables.

### GET Request

All you need is the API end point and the items to query

	$options = array(
		'screen_name' => 'screen_name',
	);
	
	$auth->get('https://api.twitter.com/1.1/statuses/user_timeline.json', $options);
	
You can omit the `$options` array if your query doesn't require one.

### POST Request

Very similar to GET requests:

	$options = array(
		'status' => 'This is your status',
	);
	
	$auth->get('https://api.twitter.com/1.1/statuses/update.json', $options);
	
## Future
I'll extend this further so it is complete in all featured (i.e. xAuth and other signature building methods). However, most of the time, this is all that is needed from a Twitter oAuth class.

Please let me know of any bugs or ways that this can be improved.