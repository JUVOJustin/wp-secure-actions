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
any secure_actions have expired or reached their limit and deletes it.

```php
register_deactivation_hook(__FILE__, function()  {
    \WordPressSecureActions\Manager::init();
});
``` 

In some cases you want to change the cleanup functions behaviour. The following examples demonstrate how to use the `secure_action_cleanup` filter.
```php
// Disable cleanup
add_filter( 'secure_action_cleanup', function() {
    return false;
}, 10, 3 );

// Exclude based on name
add_filter( 'secure_action_cleanup', 'whitelist_actions', 10, 3 );  
function whitelist_actions(bool $delete, Action $action, string $name) {  
    if ($name === "my_action") {
        return false;
    }
    return $delete;
} 
``` 

This example creates an action that sends an email to users who updated their profile. In this example the action is executed immediatly but you can execute it any time later within its expiration interval.
```php
// Create action at any time after init hook was executed  
add_action( 'profile_update', 'create_action', 10, 2 );  
function create_action( $user_id, $old_user_data ) { 
    $user = get_userdata( $user_id ); 
    $key = Manager::add_action("send_mail_$user_id", "wp_mail",[
        $user->user_email,  
        "Secure action executed", "The secure action was executed successfully." 
        ]
    );     
    // Execute the stored action any time later  
    Manager::execute_action($key);  
}  
```  

### Composer
```sh
composer require juvo/wp-secure-actions
```