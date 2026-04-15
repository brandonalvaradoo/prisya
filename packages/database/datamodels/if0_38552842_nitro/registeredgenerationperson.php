<?php

use Datarel\AvatarRel;
use Datarel\DatarelResult;

class RegisteredGenerationPerson extends Database\Datamodel
{
    protected $no_control;
    protected $fullname;
    protected $person_type_id;
    protected $is_a_student;
    protected $phonenumber;
    protected $creationtime;
    protected $avatar_file_name;

    public function SetNoControl($value)
    {
        $this->no_control = $value;
    }

    public function SetFullname($value)
    {
        $this->fullname = $value;
    }

    public function SetPersonTypeId($value)
    {
        $this->person_type_id = $value;
    }

    public function SetIsAStudent($value)
    {
        $this->is_a_student = $value;
    }

    public function SetPhonenumber($value)
    {
        $this->phonenumber = $value;
    }

    public function SetCreationTime($value)
    {
        $this->creationtime = $value;
    }

    public function SetCreationTimeToNow()
    {
        $this->creationtime = date('Y-m-d H:i:s');
    }

    public function GetNoControl()
    {
        return $this->no_control;
    }

    public function GetFullname()
    {
        return $this->fullname;
    }

    public function GetPersonTypeId()
    {
        return $this->person_type_id;
    }

    public function GetIsAStudent()
    {
        return $this->is_a_student;
    }

    public function GetPhonenumber()
    {
        return $this->phonenumber;
    }

    public function GetCreationTime()
    {
        return $this->creationtime;
    }

    public function GetAvatarFileName()
    {
        return $this->avatar_file_name;
    }

    public function SetAvatarFileName($value)
    {
        $this->avatar_file_name = $value;
    }

    public function GetPassLiquidatedCount()
    {
        $query = "
            SELECT COUNT(*) AS total_liquidated
            FROM (
                SELECT rgp.id
                FROM registered_generation_people rgp
                JOIN payments p ON p.registered_person_made_pay_id = rgp.id AND p.payment_to_pass_id = rgp.person_type_id
                JOIN person_types pt ON rgp.person_type_id = pt.id
                GROUP BY rgp.id, rgp.fullname, pt.price
                HAVING SUM(p.amount) >= pt.price
            ) AS subconsulta;
        ";

        $result = $this->GetDatabase()->connect()->query($query);
        return $result ? $result->fetchColumn() : 0;
    }

    public function GetVIPPassLiquidatedCount()
    {
        $query = "
            SELECT COUNT(*) AS all_vip
            FROM (
                SELECT rgp.id
                FROM registered_generation_people rgp
                JOIN payments p ON p.registered_person_made_pay_id = rgp.id AND p.payment_to_pass_id = rgp.person_type_id
                JOIN person_types pt ON rgp.person_type_id = pt.id
                GROUP BY rgp.id, rgp.fullname, pt.price, pt.is_vip
                HAVING SUM(p.amount) >= pt.price AND pt.is_vip = 1
            ) AS subconsulta;
        ";

        $result = $this->GetDatabase()->connect()->query($query);
        return $result ? $result->fetchColumn() : 0;
    }

    public function GetAllPeopleLiquidatedInfo()
    {
        $query = "
            SELECT 
            rgp.id AS person_id,
            rgp.fullname,
            rgp.phonenumber,
            pt.name as pass_name,
            pt.id as pass_id,
            CASE WHEN pt.id = 6 THEN (
                SELECT anfitrion.fullname
                FROM guests g
                JOIN registered_generation_people anfitrion ON anfitrion.id = g.guest_of_person_id
                WHERE g.guest_person_id = rgp.id
                LIMIT 1
            )
            ELSE NULL
            END AS parent_name

            FROM registered_generation_people rgp
            JOIN payments p 
                ON p.registered_person_made_pay_id = rgp.id 
                AND p.payment_to_pass_id = rgp.person_type_id
            JOIN person_types pt 
                ON rgp.person_type_id = pt.id
            GROUP BY rgp.id, rgp.fullname, rgp.phonenumber, pt.id, pt.name, pt.price
            HAVING SUM(p.amount) >= pt.price;
        ";

        $result = $this->GetDatabase()->connect()->query($query);
        return $result ? $result->fetchAll(\PDO::FETCH_ASSOC) : [];
    }


    /**
     * Retrieves information about a registered person associated with a specific access card ID.
     *
     * This method queries the database to fetch details such as the person's ID, full name, phone number,
     * pass type, and, if applicable, the name of the parent (anfitrion) for guests.
     *
     * @param string $accesscardID The ID of the access card to search for.
     * @return array|null An associative array containing person and pass details if found, or null if not found.
     */
    public function GetWhereAccesscardID($accesscardID)
    {
        $query = "
            SELECT 
            rgp.id AS person_id,
            rgp.fullname,
            rgp.phonenumber,
            pt.name AS pass_name,
            pt.id AS pass_id,
            acc.consecutive_number AS consecutive_number,
            CASE 
                WHEN pt.id = 6 THEN (
                    SELECT anfitrion.fullname
                    FROM guests g
                    JOIN registered_generation_people anfitrion ON anfitrion.id = g.guest_of_person_id
                    WHERE g.guest_person_id = rgp.id
                    LIMIT 1
                )
                ELSE NULL
            END AS parent_name
            FROM accesscards acc
            JOIN registered_generation_people rgp ON acc.registered_person_id = rgp.id
            JOIN payments p ON p.registered_person_made_pay_id = rgp.id AND p.payment_to_pass_id = rgp.person_type_id
            JOIN person_types pt ON rgp.person_type_id = pt.id
            WHERE acc.id = :accesscardID;
        ";

        $stmt = $this->GetDatabase()->connect()->prepare($query);
        $stmt->bindParam(':accesscardID', $accesscardID, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);


        if($result === false)
        {
            return null;
        }

        return $result;
    }

    /**
     * Updates the avatar for the current user.
     *
     * This method attempts to upload a new avatar image for the user. If the upload is successful,
     * it updates the user's avatar filename and saves the changes to the database.
     *
     * @param mixed $new_avatar The new avatar file or data to be uploaded.
     * @return DatarelResult|bool Returns a DatarelResult object on success or failure, or false on error.
     */
    public function UpdateAvatar($new_avatar) : DatarelResult|bool
    {
        $oldAvatar = $this->GetAvatarFileName();
        $avatarRel = new AvatarRel();
        $tryUpload = $avatarRel->UploadAvatar($new_avatar, $this->GetID(), $oldAvatar);

        if($tryUpload->Success())
        {
            $this->SetAvatarFileName($tryUpload->GetUploadedFileFullName());
            $this->SaveAtDB();
        }

        return $tryUpload;
    }
}