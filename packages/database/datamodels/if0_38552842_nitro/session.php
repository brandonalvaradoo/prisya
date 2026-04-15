<?php

use Datarel\AvatarRel;
use Datarel\DatarelResult;

require_once "user.php";

class Session extends User
{
    public const SESSION_SAVING_KEY = "userdatamodelver1";

    public function __construct(User|null $user=null)
    {
        parent::__construct();
        
        /*if($this->Active() && !is_null($user))
        {
            throw new Exception("Session already active. Cannot create a new session with a user. Try to fetch the user instead or logout to create a new session.");
        }*/


        if($user != null)
        {
            $this->SaveSession($user);
            return;
        }
        
        if($this->Active())
        {
            $this->FetchById($_SESSION[self::SESSION_SAVING_KEY]);
        }
    }

    public static function Start()
    {
        session_start();
    }

    public function SaveSession(User $user)
    {
        $_SESSION[self::SESSION_SAVING_KEY] = $user->GetID();
        $this->FetchById($user->GetID());
    }

    public static function Active()
    {
        return isset($_SESSION[self::SESSION_SAVING_KEY]);
    }

    public function UpdateAvatar($new_avatar) : DatarelResult|bool
    {
        $oldAvatar = $this->GetAvatar();
        $avatarRel = new AvatarRel();
        $tryUpload = $avatarRel->UploadAvatar($new_avatar, $this->GetID(), $oldAvatar);

        if($tryUpload->Success())
        {
            $this->SetAvatar($tryUpload->GetUploadedFileFullName());
            $this->SaveAtDB();
        }

        return $tryUpload;
    }

    public function Logout()
    {
        session_unset();
        session_destroy();
    }
}