<?php

class TempUser extends Database\Datamodel
{
    protected $name;
    protected $lastname;
    protected $username;
    protected $email;
    protected $password;
    protected $verification_code;
    protected $creation_date;

    public function SetName($name)
    {
        $this->name = $name;
    }

    public function SetLastname($lastname)
    {
        $this->lastname = $lastname;
    }

    public function SetUsername($username)
    {
        $this->username = $username;
    }

    public function SetEmail($email)
    {
        $this->email = $email;
    }

    public function SetPassword($password)
    {
        $this->password = $password;
    }

    public function SetVerificationCode($verification_code)
    {
        $this->verification_code = $verification_code;
    }

    public function SetCreationDate($creation_date)
    {
        $this->creation_date = $creation_date;
    }

    public function SetCreationDateToNow()
    {
        $this->creation_date = date('Y-m-d H:i:s');
    }

    public function GetName()
    {
        return $this->name;
    }

    public function GetLastname()
    {
        return $this->lastname;
    }

    public function GetUsername()
    {
        return $this->username;
    }

    public function GetEmail()
    {
        return $this->email;
    }

    public function GetPassword()
    {
        return $this->password;
    }

    public function GetVerificationCode()
    {
        return $this->verification_code;
    }

    public function GetCreationDate()
    {
        return $this->creation_date;
    }

    public function MaterializeAsUser()
    {
        $user = new User();
        $user->SetName($this->name);
        $user->SetLastname($this->lastname);
        $user->SetUsername($this->username);
        $user->SetEmail($this->email);
        $user->SetPassword($this->password);
        $user->SaveAtDB();
        return $user;
    }
}