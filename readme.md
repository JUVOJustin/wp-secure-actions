[![PHP Test/Analyse](https://github.com/JUVOJustin/wp-secure-actions/actions/workflows/php.yml/badge.svg)](https://github.com/JUVOJustin/wp-secure-actions/actions/workflows/php.yml)

# WordPress Secure Actions

This library allows you to create verifiable actions with an expiration date and an execution limit. Possible use cases:

 - One-time-links
 - Trackable links (number of clicks)
 - Slack-like magic login that authenticates a user via e-mail
 - Secured download links to avoid direct file access
 - Double opt-in process

Secure Actions uses its own database table to avoid further pollution and cleans up after itself. 

## Installation
Simply instantiate the `Manager` class as early as possible by adding the following snippet to your functions.php or  an early executed plugin file:

```php
 // Init Secure Actions  
 \juvo\WordPressSecureActions\Manager::getInstance();
```
Secure Actions will take care it is only loaded once. It will automatically register the cleanup cron job and default URL handling process.

## Usage

| Parameter     | Type             | | Description                                                                                   
|---------------|------------------|---|-----------------------------------------------------------------------------------------------|
| `$name`       | `string`         | Required | Unique name to identify the action e.g. in filters                                            |
| `$callback`   | `string` `array` | Required | Callback action to execution whenever the action is triggered.                                |
| `$args`       | `array`          | Optional| The parameters to be passed to the callback, as an indexed array. Defaults to `array()`.      |
| `$expiration` | `int`            | Optional| Action expiration interval in seconds. Defaults to `-1`.                                      |
| `$limit`      | `int`            | Optional | Action execution limit. Defaults to `-1`.                                                     |
| `$persistent` | `bool`           | Optional | Determines if an action should be deleted when expired or limit reached. Defaults to `false`. |
| `key`         | `string`         | Optional | Sets the given password instead if choosing a random one                                      |


## Example
### Create and execute Action
This example creates an action that sends an email to users who updated their profile. In this example, the action is executed immediately, but you can execute it any time later within its expiration interval.
```php
// Create action at any time after init hook was executed  
add_action( 'profile_update', 'createAction', 10, 2 );  
function createAction( $user_id, $old_user_data ) { 
    $user = get_userdata( $user_id );
    $secActionsManager= \juvo\WordPressSecureActions\Manager::getInstance();

	// Create action
    $key = $secActionsManager->addAction(
        "send_mail_$user_id", // name
        "wp_mail", // callback
        [
            $user->user_email, // arg1
            "Secure action executed", // arg2
             "The secure action was executed successfully." // arg3
        ]
    );      
} 

// Execute the stored action any time later 
(\juvo\WordPressSecureActions\Manager::getInstance())->executeAction($key); 
```
### Use action in URL
In the following example, we are going to inform a WordPress user if his profile was updated. The user will receive an email containing a login link that automatically redirects him to the profile page. Since the callback function has no return value, the is code is not able to detect a successful execution and cannot automatically increment the counter. Therefore, we have to do it manually.
```php
// Create action at any time after init hook was executed  
add_action( 'profile_update', 'createAction', 10, 2 );  
function createAction( $user_id, $old_user_data ) { 
	$user = get_userdata( $user_id );
	$secActionsManager= \juvo\WordPressSecureActions\Manager::getInstance();

	// Create Action
	$key = $secActionsManager->addAction(
		"send_mail_$user_id", // name
		"ourCallbackFunction", // callback
		[
			$user, // arg1
		]
	);

	// Generate url with helper function to automatically execute action 
	$actionUrl = $secActionsManager->buildActionUrl($key);

	// Send mail containing the url
	wp_mail(  
	  $user->user_email,  
	  "Profile Updated",  
	  "Your profile was updated click here to check it out $actionUrl"  
	);
}

// Callback that executes when link is clicked
function ourCallbackFunction(WP_User $user, Action $action) {
	wp_set_auth_cookie($user->ID, false);
	do_action('wp_login', $user->user_login, $user);

	// Manually increment count because this callback function has no return value
	\juvo\WordPressSecureActions\Manager::getInstance()->incrementCount($action);

	wp_safe_redirect(get_edit_profile_url($user->ID)); // Redirect to profile page
	exit;
} 
```

## Advanced usages
### Cleanup
In some cases, you want to change the cleanup functions behaviour. The following examples demonstrate how to use the `secure_action_cleanup` filter.
```php
// Disable cleanup
add_filter( 'secure_action_cleanup', function() {
	return false;
}, 10, 3 );

// Exclude based on name
add_filter( 'secure_action_cleanup', 'whitelistActions', 10, 3 );  
function whitelistActions(bool $delete, Action $action, string $name) {  
	if ($name === "my_action") {
		return false;
	}
	return $delete;
} 
```
### Action URL redirect
If you use the secure action URL feature, the user will be redirected to the front page after the execution. It is possible to change that behaviour by hooking into the following filter.

| Parameter | Type | | Description
|---|---|---|---|
| `$url`| `string` | Required | URL to redirect to. Defaults to `get_site_url()` |
| `$action`| `\juvo\WordPressSecureActions\Action`\|`WP_Error` | Optional | Action lookup result from the db. If your action exceeded some limits and is not using the "persistent" flag, this parameter will most likely be an `WP_Error` instance because the deletion workflow is triggered during the action execution. |
| `$result` | `mixed` | Optional| The action´s execution result. Will most likely be an instance of `WP_Error` if the action exceeded some limits.|
```php
apply_filters( 'juvo_secure_actions_catch_action_redirect', $url, $action, $executionResult);
```
## Security
### 1. Key Generation and Storage:
- **Unique Keys**: The library generates unique keys for each action using the WordPress core function `wp_generate_password()`. This ensures randomness and unpredictability for the keys, especially with a length of 28 characters.
- **Hashing**: Before storing the key in the database, it is hashed using the WordPress `PasswordHash` class, which implements a portable PHP password hashing framework. This means the original key is never stored in plain text, further protecting against potential database breaches.
- **Verification**: The hashed key is used for verification during the execution of actions, ensuring only the person with the original key can execute the given action.

### 2. Action Execution Verifications:
- **Key Validity**: During the execution of an action, the provided key is verified against the hashed key in the database. If there's a mismatch, the action will not proceed.
- **Expiration**: Each action can have an expiration time. If an attempt is made to execute the action after its expiry, the attempt will be declined, and the action will be deleted.
- **Execution Limits**: A limit can be set on the number of times an action can be executed. Once this limit is reached, further execution attempts are declined.
- **Callback Integrity**: The library ensures the callback associated with the action is callable before proceeding with the execution. This prevents potential misconfigurations or tampered callback data from causing unexpected behaviors.

### 3. Error Handling:
- The library returns `WP_Error` objects in cases of execution failures, ensuring the end user gets a descriptive message about what went wrong without exposing sensitive system details.

### 4. Recommendations:
- **Key Storage**: Make sure, to never store the plain keys as they give direct access to the underlying process.
- **Limit Persistent Actions**: The library provides an option for actions to persist even after reaching their limits or expiration. While this can be useful in certain scenarios, it's recommended to use this feature judiciously.

## Composer
```sh
composer require juvo/wp-secure-actions
```
