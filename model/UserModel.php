<?php

class User
{
    // Properties
    public $id;
    public $name;
    public $phone;
    public $address;
    public $permission;
    public $email;
    public $username;
    public $password;

    /**
     * Constructor
     */
    public function __construct($data = [])
    {
        if (!empty($data)) {
            $this->id = $data['id'] ?? '';
            $this->name = $data['name'] ?? '';
            $this->phone = $data['phone'] ?? '';
            $this->address = $data['address'] ?? '';
            $this->permission = $data['permission'] ?? 'user';
            $this->email = $data['email'] ?? '';
            $this->username = $data['username'] ?? '';
            $this->password = $data['password'] ?? '';
        }
    }

    /**
     * Convert object to array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'permission' => $this->permission,
            'email' => $this->email,
            'username' => $this->username,
            'password' => $this->password
        ];
    }

    /**
     * Convert object to array for database insert/update (without id)
     */
    public function toArrayForDB()
    {
        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'permission' => $this->permission,
            'email' => $this->email,
            'username' => $this->username
        ];

        // Only include password if it's set
        if (!empty($this->password)) {
            $data['password'] = password_hash($this->password, PASSWORD_DEFAULT);
        }

        return $data;
    }

    /**
     * Set properties from array
     */
    public function fromArray($data)
    {
        $this->id = $data['id'] ?? $this->id;
        $this->name = $data['name'] ?? $this->name;
        $this->phone = $data['phone'] ?? $this->phone;
        $this->address = $data['address'] ?? $this->address;
        $this->permission = $data['permission'] ?? $this->permission;
        $this->email = $data['email'] ?? $this->email;
        $this->username = $data['username'] ?? $this->username;

        // Only set password if provided (for updates)
        if (isset($data['password']) && !empty($data['password'])) {
            $this->password = $data['password'];
        }
    }

    /**
     * Validate user data
     */
    public function validate($isUpdate = false)
    {
        $errors = [];

        // Validate name
        if (empty($this->name) || strlen(trim($this->name)) < 2) {
            $errors['name'] = 'Tên phải có ít nhất 2 ký tự';
        }

        // Validate email
        if (empty($this->email) || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email không hợp lệ';
        }

        // Validate username
        if (empty($this->username) || strlen(trim($this->username)) < 3) {
            $errors['username'] = 'Tên đăng nhập phải có ít nhất 3 ký tự';
        }

        // Validate password (only for new users or when password is provided)
        if (!$isUpdate || !empty($this->password)) {
            if (empty($this->password) || strlen($this->password) < 6) {
                $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự';
            }
        }

        // Validate phone
        if (!empty($this->phone) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $this->phone)) {
            $errors['phone'] = 'Số điện thoại không hợp lệ';
        }

        // Validate permission
        if (!in_array($this->permission, ['admin', 'user'])) {
            $errors['permission'] = 'Quyền không hợp lệ';
        }

        return $errors;
    }

    /**
     * Sanitize data
     */
    public function sanitize()
    {
        $this->name = htmlspecialchars(strip_tags(trim($this->name)));
        $this->phone = htmlspecialchars(strip_tags(trim($this->phone)));
        $this->address = htmlspecialchars(strip_tags(trim($this->address)));
        $this->permission = htmlspecialchars(strip_tags(trim($this->permission)));
        $this->email = htmlspecialchars(strip_tags(trim($this->email)));
        $this->username = htmlspecialchars(strip_tags(trim($this->username)));
    }
}
?>