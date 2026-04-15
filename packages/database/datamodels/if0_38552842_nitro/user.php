<?php

Database\Database::ResolveDatamodelFromRoot('role');

class User extends Database\Datamodel
{
    protected $username;
    protected $name;
    protected $lastname;
    protected $email;
    protected $password;
    protected $accountstatus;
    protected $creationtime;
    protected $avatar;

    /**
     * Find a user by their username or email.
     *
     * This method searches for a user in the database by their username or email.
     * If a user is found with the given username or email, the corresponding user
     * data is fetched and the method returns true. If no user is found, the method
     * returns false.
     *
     * @param string $seeker The username or email to search for.
     * @return bool True if a user is found, false otherwise.
     */
    public function Find(string $seeker) : bool
    {
        if($this->ExistItemInColumn("username", $seeker))
        {
            $this->Where("username", $seeker);
            return true;
        }

        if($this->ExistItemInColumn("email", strtolower($seeker)))
        {
            $this->Where("email", strtolower($seeker));
            return true;
        }

        return false;
    }

    public function SetUsername(string $username) : bool
    {
        if(!Validator::ValidateUsername($username))
        {
            return false;
        }

        $this->username = $username;
        return true;
    }

    public function SetName(string $name) : bool
    {
        if(!Validator::ValidateName($name))
        {
            return false;
        }

        $this->name = $name;
        return true;
    }

    public function SetLastName(string $lastname) : bool
    {
        if(!Validator::ValidateName($lastname))
        {
            return false;
        }

        $this->lastname = $lastname;
        return true;
    }

    public function SetEmail(string $email) : bool
    {
        if(!Validator::ValidateEmail($email))
        {
            return false;
        }

        $this->email = $email;
        return true;
    }

    public function SetPassword(string $password) : bool
    {
        if(!Validator::ValidatePassword($password))
        {
            return false;
        }

        $this->password = Encryptor::Encrypt($password);
        return true;
    }

    public function Ban() : bool
    {
        $this->accountstatus = 0;
        return true;
    }

    public function Unban() : bool
    {
        $this->accountstatus = 1;
        return true;
    }

    public function SetAvatar(string $avatar) : bool|null
    {
        $this->avatar = $avatar;
        return true;
    }

    /**
  ________        __    __                       
 /  _____/  _____/  |__/  |_  ___________  ______
/   \  ____/ __ \   __\   __\/ __ \_  __ \/  ___/
\    \_\  \  ___/|  |  |  | \  ___/|  | \/\___ \ 
 \______  /\___  >__|  |__|  \___  >__|  /____  >
        \/     \/                \/           \/ 

     */

    public function GetUsername() : string
    {
        return $this->username;
    }

    public function GetName() : string
    {
        return $this->name;
    }

    public function GetLastName() : string
    {
        return $this->lastname;
    }

    public function GetEmail() : string
    {
        return $this->email;
    }

    public function GetAccountStatus() : int
    {
        return $this->accountstatus;
    }

    public function GetCreationTime() : string
    {
        return $this->creationtime;
    }

    public function GetAvatar() : string|null
    {
        return $this->avatar;
    }

    public function GetFullName() : string
    {
        return $this->name . ' ' . $this->lastname;
    }

    public function GetRole() : Role|null
    {
        $userRoleFinder = new UserRole();
        $userRoleFinder->Where('user_id', $this->GetID());
        
        return $userRoleFinder->GetRoleObject();
    }

    public function GetAvatarWidget() : string
    {
        $avatarRel = new Datarel\AvatarRel();
        $result = '
                <div class="avatar_container" style="
            width: 100%;
            height: 100%;
            overflow: hidden;
            position: absolute;
        ">
            <img class="avatar" style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 100%;
            "
            src="'
            . ($avatarRel->GetFullDatarelObjectPath() . $this->GetAvatar())
            . '" alt="' . $this->GetFullName() . '">
        </div>
        ';
        return $result;
    }

    /**
     * Validates the provided username/email and password against the stored values.
     *
     * @param string $user The username or email to validate.
     * @param string $password The password to validate.
     * @return bool Returns true if the provided username/email and password match the stored values, false otherwise.
     */
    public function Validate(string $user, string $password) : bool
    {
        return
        ($this->username === $user || $this->email === $user)
        && Encryptor::PasswordVerify($password, $this->password);
    }




    /**
     * Logs in a user by verifying the provided password and user status.
     *
     * @param string $password The password provided by the user for authentication.
     * 
     * @return Session|int Returns a Session object if login is successful, or an integer error code:
     *                     - 2: If the username or email does not exist.
     *                     - 3: If the password verification fails.
     *                     - 4: If the account status is inactive.
     *                     - 5: If validation fails for any other reason.
     */
    public function Login(string $password) : Session|int
    {
        if(!$this->ExistItemInColumn("username", $this->username)) return 2;

        if($this->accountstatus === 0) return 4;

        if(!Encryptor::PasswordVerify($password, $this->password)) return 3;

        if($this->Validate($this->username, $password))
        {
            return new Session($this);
        }
        
        return 5;
    }
}