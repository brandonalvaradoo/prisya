<?php
Database\Database::ResolveDatamodelFromRoot('role');

class UserRole extends Database\Datamodel
{
    protected $user_id;
    protected $role_id;
    protected $creation_time;
    protected $active;
    protected $expiration_time;
    protected $reason;


    public function GetUserID() : int
    {
        return $this->user_id;
    }

    public function GetRoleID() : int
    {
        return $this->role_id;
    }

    public function GetCreationTime() : string
    {
        return $this->creation_time;
    }

    public function GetActive() : bool
    {
        return $this->active;
    }

    public function GetExpirationTime() : string
    {
        return $this->expiration_time;
    }

    public function GetReason() : string
    {
        return $this->reason;
    }

    public function GetRoleObject() : Role|null
    {
        $roleFetcher = new Role();
        $roleFetcher->FetchById($this->GetUserID());

        return $roleFetcher;
    }
}