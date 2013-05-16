<?php

namespace Directus\Auth;

class Provider {

    public static $SESSION_KEY = "auth_user";

    /**
     * Change where authentication information is stored on the session array.
     * @param string $key
     * @return  null
     */
    public static function setSessionKey($key) {
        self::$SESSION_KEY = $key;
    }

    /**
     * Attempt authentication after user submission.
     * @param  int $uid             The User account's ID.
     * @param  string $password        The User account's (actual) hashed password string.
     * @param  string $salt            The User account's salt string.
     * @param  string $passwordAttempt The User's attempted, unhashed password string.
     * @return boolean
     */
    public static function login($uid, $password, $salt, $passwordAttempt) {
        $hashedPasswordAttempt = self::hashPassword($passwordAttempt, $salt);
        if($password === $hashedPasswordAttempt) {
            self::completeLogin($uid);
            return true;
        }
        return false;
    }

    /**
     * De-authenticate the logged-in user.
     * @return null
     * @throws  \Directus\Auth\UserIsntLoggedInException
     */
    public static function logout() {
        if(!self::loggedIn()) {
            throw new UserIsntLoggedInException("Attempting to de-authenticate a user when a user isn't authenticated.");
        }
        $_SESSION[self::$SESSION_KEY] = array();
    }

    /**
     * Check if a user is logged in.
     * @return boolean
     */
    public static function loggedIn() {
        if("" === session_id())
            session_start();
        return isset($_SESSION[self::$SESSION_KEY]) && !empty($_SESSION[self::$SESSION_KEY]);
    }

    /**
     * Retrieve metadata about the currently logged in user.
     * @return array Authenticated user metadata.
     * @throws  \Directus\Auth\UserIsntLoggedInException
     */
    public static function getUserInfo() {
        if(!self::loggedIn()) {
            throw new UserIsntLoggedInException("Attempting to inspect the authenticated user when a user isn't authenticated.");
        }
        return $_SESSION[self::$SESSION_KEY];
    }

    /**
     * After a successful login attempt, registers the user in the session.
     * @param  int $uid The User account's ID.
     * @return null
     * @throws  \Directus\Auth\UserAlreadyLoggedInException
     */
    private static function completeLogin($uid) {
        if(self::loggedIn()) {
            throw new UserAlreadyLoggedInException("Attempting to authenticate a user when a user is already authenticated.");
        }
        $user = array( 'id' => $uid );
        $_SESSION[self::$SESSION_KEY] = $user;
    }

    /**
     * Run the hashing algorithm on a password and salt value.
     * @param  string $password
     * @param  string $salt
     * @return string
     */
    public static function hashPassword($password, $salt) {
        return sha1( $salt . $password );
        // This is not working --
        $PHPass = new \Directus\Auth\PasswordHash(8, true);
        return $PHPass->HashPassword( $salt . $password );
    }

}

/**
 * Exceptions
 */

class UserAlreadyLoggedInException extends \Exception {}

class UserIsntLoggedInException extends \Exception {}