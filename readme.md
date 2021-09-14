# WordPress Secure Actions

This package allows you to create verifiable actions with an expiration date and an execution limit. Usecases might be
the generation of onetimelinks, handling downloads or e-mail logins for users.

## Usage

| Parameter | Type | | Description
|---|---|---|---|
| `$name`| `string` | Required | Unique name to identify the action e.g. in filters |
| `$callback`| `string` `array` | Required | Callback action to execution whenever the action is triggered. |
| `$args` | `array` | Optional| The parameters to be passed to the callback, as an indexed array. Defaults to `array()`. |
| `$expiration` | `int` | Optional| Action expiration interval in seconds. Defaults to `-1`. |
| `$limit` | `int` | Optional | Action execution limit. Defaults to `-1`. |
| `$persistent` | `bool` | Optional | Determines if an action should be deleted when expired or limit reached. Defaults to `false`. |

The init function registers a cleanup cron job which checks if
any secure_actions have expired or reached their limit and maybe deletes it. The function is also responssible for creating the database table. A good place to call it would be inside the `register_activation_hook`.

```php
register_activation_hook(__FILE__, function()  {
    \juvo\WordPressSecureActions\Manager::init();
});
``` 

In some cases you want to change the cleanup functions behaviour. The following examples demonstrate how to use the `secure_action_cleanup` filter.
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

This example creates an action that sends an email to users who updated their profile. In this example the action is executed immediatly but you can execute it any time later within its expiration interval.
```php
// Create action at any time after init hook was executed  
add_action( 'profile_update', 'createAction', 10, 2 );  
function createAction( $user_id, $old_user_data ) { 
    $user = get_userdata( $user_id ); 
    $key = Manager::addAction(
        "send_mail_$user_id", // name
        "wp_mail", // callback
        [
            $user->user_email, // arg1
            "Secure action executed", // arg2
             "The secure action was executed successfully." // arg3
        ]
    );     
    
    // Execute the stored action any time later  
    Manager::executeAction($key);  
}  
```  

### Composer
```sh
composer require juvo/wp-secure-actions
```