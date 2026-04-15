<?php

class Role extends Database\Datamodel
{
    protected $name;
    protected $description;
    protected $god;
    protected $can_edit_paths;
    protected $can_edit_stops;
    protected $can_edit_users;
    protected $can_edit_below_roles;

    public function GetName() : string
    {
        return $this->name;
    }

    public function GetDescription() : string
    {
        return $this->description;
    }

    public function GetGod() : bool
    {
        return $this->god;
    }

    public function GetCanEditPaths() : bool
    {
        return $this->can_edit_paths;
    }

    public function GetCanEditStops() : bool
    {
        return $this->can_edit_stops;
    }

    public function GetCanEditUsers() : bool
    {
        return $this->can_edit_users;
    }

    public function GetCanEditBelowRoles() : bool
    {
        return $this->can_edit_below_roles;
    }
}