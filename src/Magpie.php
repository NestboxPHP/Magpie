<?php

declare(strict_types=1);

namespace NestboxPHP\Magpie;

use NestboxPHP\Nestbox\Nestbox;

class Magpie extends Nestbox
{
    final public const PACKAGE_NAME = 'magpie';
    // settings variables
    public string $magpieUsersTable = 'users';
    public string $magpieUserColumn = 'username';
    public string $magpiePermissionsTable = 'magpie_permissions';
    public string $magpiePermissionAssignmentsTable = 'magpie_permission_assignments';
    public string $magpieRolesTable = 'magpie_roles';
    public string $magpieRoleAssignmentsTable = 'magpie_role_assignments';

    protected function create_class_table_magpie_permissions(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS `magpie_permissions` (
                    `permission_id` INT NOT NULL AUTO_INCREMENT ,
                    `permission_name` VARCHAR(63) NOT NULL ,
                    `permission_description` VARCHAR(255) NOT NULL ,
                    PRIMARY KEY (`permission_id`)) ENGINE = InnoDB; 
                ) ENGINE = InnoDB DEFAULT CHARSET=UTF8MB4 COLLATE=utf8mb4_general_ci;";

        return $this->query_execute(query: $sql);
    }

    protected function create_class_table_magpie_permission_assignments(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS `magpie_permission_assignments` (
                    `assignment_id` INT NOT NULL AUTO_INCREMENT ,
                    `permission_id` INT NOT NULL ,
                    `user_id` VARCHAR( 125 ) NOT NULL ,
                    PRIMARY KEY ( `assignment_id` )
                ) ENGINE = InnoDB DEFAULT CHARSET=UTF8MB4 COLLATE=utf8mb4_general_ci;";

        return $this->query_execute(query: $sql);
    }

    protected function create_class_table_magpie_roles(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->magpieRolesTable}` (
                    `role_id` INT NOT NULL AUTO_INCREMENT ,
                    `role_name` VARCHAR(63) NOT NULL ,
                    `role_description` VARCHAR(255) NOT NULL ,
                    PRIMARY KEY (`role_id`)) ENGINE = InnoDB; 
                ) ENGINE = InnoDB DEFAULT CHARSET=UTF8MB4 COLLATE=utf8mb4_general_ci;";

        return $this->query_execute(query: $sql);
    }

    protected function create_class_table_magpie_role_assignments(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->magpieRoleAssignmentsTable}` (
                    `assignment_id` INT NOT NULL AUTO_INCREMENT ,
                    `permission_id` INT NOT NULL ,
                    `user_id` VARCHAR( 125 ) NOT NULL ,
                    PRIMARY KEY ( `assignment_id` )
                ) ENGINE = InnoDB DEFAULT CHARSET=UTF8MB4 COLLATE=utf8mb4_general_ci;";

        return $this->query_execute(query: $sql);
    }

    public function list_permissions(bool $flatten = false): array
    {
        if (!$flatten) return $this->select("magpie_permissions");

        $permissions = [];
        foreach ($this->select("magpie_permissions", orderBy: ["permission_name" => "ASC"]) as $permission) {
            $permissions[$permission["permission_name"]] = $permission["permission_description"];
        }
        return $permissions;
    }

    public function permission_create(): bool
    {
        // create snake_case id from permission name
        // check no other permission with name exists
        // add permission to permissions table
        return true;
    }

    public function permission_rename(): bool
    {
        // check no other permission with name exists
        // update permission name and id
        // update permission assignments for roles
        // update permission assignments for users
        return true;
    }

    public function permission_delete(): bool
    {
        // delete permission form permissions table
        // delete assigned permissions from users
        // delete grouped permissions from roles
        return true;
    }

    public function list_roles(): array
    {
        return $this->select($this->magpieRolesTable);
    }

    public function role_create(): bool
    {
        // create snake_case id from role name
        // check no other role with name exists
        // add role to role table
        return true;
    }

    public function role_rename(): bool
    {
        // check no other role with name exists
        // update role name and id
        // update role assignment names for users
        return true;
    }

    public function role_delete(): bool
    {
        // delete role from roles table
        // delete assigned roles from users
        return true;
    }

    public function role_add_permission(): bool
    {
        return true;
    }

    public function role_remove_permission(): bool
    {
        return true;
    }

    public function user_add_permission(): bool
    {
        return true;
    }

    public function user_remove_permission(): bool
    {
        return true;
    }

    public function user_add_role(): bool
    {
        return true;
    }

    public function user_remove_role(): bool
    {
        return true;
    }

    /**
     * Returns `true` if `$userId` has `$permissionName`, otherwise `false`
     *
     * @param string $userId
     * @param string $permissionName
     * @return bool
     */
    public function user_has_permission(string $userId, string $permissionName): bool
    {
        return ($this->get_user($userId)[$permissionName] ?? false) ? true : false;
    }

    public function list_users(array $showColumns = [], bool $groupPermissions = true, string $userId = null): array
    {
        if (!$showColumns) $showColumns = [$this->magpieUserColumn];
        $users = [];
        $select = [];

        foreach ($this->list_permissions() as $permission) {
            list("permission_id" => $id, "permission_name" => $name) = $permission;
            $select[] = "MAX(asgn.permission_id = $id) AS `$name`";
        }
        $select = implode(", ", $select);

        $where = ($userId) ? "WHERE `usrs`.`$this->magpieUserColumn` = :$this->magpieUserColumn" : "";
        $params = ($userId) ? [$this->magpieUserColumn => $userId] : [];
        $sql = "SELECT `usrs`.*, $select
                FROM `$this->magpieUsersTable` usrs
                LEFT JOIN `$this->magpiePermissionAssignmentsTable` asgn
                    ON `usrs`.`$this->magpieUserColumn` = asgn.user_id
                $where
                GROUP BY `usrs`.`$this->magpieUserColumn`
                ORDER BY `usrs`.`$this->magpieUserColumn`;";

        if (!$this->query_execute($sql, $params)) return $users;
        $usersData = $this->fetch_all_results();

        $permissionIds = array_keys($this->list_permissions(flatten: true));

        foreach ($usersData as $userData) {
            // reinstantiate arrays per user
            $user = [];
            $permissions = [];
            foreach ($userData as $key => $value) {
                // custom-defined columns
                if (in_array($key, $showColumns)) $user[$key] = $value;
                if (array_key_exists($key, $showColumns)) $user[$showColumns[$key]] = $value;
                // permissions
                if (in_array($key, $permissionIds)) {
                    if ($groupPermissions and 1 == $value) $permissions[] = $key;
                    if (!$groupPermissions) $user[$key] = $value;
                }
            }

            // save permissions to user data
            if ($groupPermissions) $user["permissions"] = implode(", ", $permissions);

            // add single user data to all users
            $users[] = $user;
        }

        return $users;
    }

    public function get_user(string $userId, array $showColumns = [], bool $groupPermissions = false): array
    {
        return $this->list_users($showColumns, $groupPermissions, $userId)[0] ?? [];
    }
}
