<?php


namespace WordPressSecureActions;


class Action_CPT
{

    const TYPE = "secure_actions";

    public static function register_post_type() {

        register_post_type( "secure_action", [
            "public" => true,
            "hierarchical" => false,
            "supports" => [
                "title",
                "editor",
                "custom-fields"
            ],
            "has_archive" => false,
            "can_export" => true,
            "delete_with_user" => false,
            
        ] );

    }

}