<?php
require_once __DIR__ . '/UserModel.php';

class Database
{
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct()
    {
        $this->loadEnv();
    }

    // Load environment variables from .env file
    private function loadEnv()
    {
        $envFile = __DIR__ . '/../.env';

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    switch ($key) {
                        case 'DB_HOST':
                            $this->host = $value;
                            break;
                        case 'DB_PORT':
                            $this->port = $value;
                            break;
                        case 'DB_DATABASE':
                            $this->db_name = $value;
                            break;
                        case 'DB_USERNAME':
                            $this->username = $value;
                            break;
                        case 'DB_PASSWORD':
                            $this->password = $value;
                            break;
                    }
                }
            }
        } else {
            // Fallback to default values if .env file doesn't exist
            $this->host = getenv('DB_HOST') ?: '127.0.0.1';
            $this->port = getenv('DB_PORT') ?: '3306';
            $this->dbname = getenv('DB_DATABASE') ?: '';
            $this->username = getenv('DB_USERNAME') ?: 'root';
            $this->password = getenv('DB_PASSWORD') ?: '';
        }
    }

    // Get database connection
    public function getConnection()
    {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    // Execute a query and return results
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $exception) {
            echo "Query error: " . $exception->getMessage();
            return false;
        }
    }

    // Fetch all records
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }

    // Fetch single record
    public function fetch($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }

    public function getBookById($bookId)
    {
        try {
            if (!is_numeric($bookId) || $bookId <= 0) {
                throw new InvalidArgumentException('Book ID must be a positive integer');
            }

            $sql = "SELECT * FROM books WHERE id = :id LIMIT 1";
            $params = ['id' => (int) $bookId];

            $result = $this->fetch($sql, $params);

            return $result ?: null;

        } catch (Exception $e) {
            error_log('Get book by ID error: ' . $e->getMessage());
            return null;
        }
    }

    public function getCategoryIdbyName($name)
    {
        $sql = "SELECT id FROM categories WHERE name = :name LIMIT 1";
        $params = ['name' => $name];
        return $this->fetch($sql, $params);
    }


    // Pagination function - Fetch records with pagination
    public function fetchWithPagination($table, $page = 1, $perPage = 15, $where = '', $params = [], $orderBy = 'id DESC')
    {
        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Build the SQL query
        $sql = "SELECT * FROM {$table}";

        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        }

        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        return $this->fetchAll($sql, $params);
    }

    // Get pagination info for books with sort functionality
    public function getBooksWithPagination($page = 1, $perPage = 18, $search = '', $category = '', $sort = 'newest')
    {
        $where = '';
        $params = [];

        // Build WHERE clause for search and category filter
        $conditions = [];

        if (!empty($search)) {
            $conditions[] = "(title LIKE :search OR author LIKE :search OR description LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($category)) {
            $conditions[] = "category = :category";
            $params['category'] = $category;
        }

        if (!empty($conditions)) {
            $where = implode(' AND ', $conditions);
        }

        // Get ORDER BY clause based on sort parameter
        $orderBy = $this->getSortClause($sort);

        // Get books for current page with custom sort
        $books = $this->fetchWithPagination('books', $page, $perPage, $where, $params, $orderBy);

        // Get total count for pagination info
        $totalBooks = $this->count('books', $where, $params);
        $totalPages = ceil($totalBooks / $perPage);

        // Calculate start and end record numbers
        $offset = ($page - 1) * $perPage;
        $start = $offset + 1;
        $end = min($offset + $perPage, $totalBooks);

        return [
            'books' => $books,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalBooks,
                'total_pages' => $totalPages,
                'start' => $start,
                'end' => $end,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
                'previous_page' => $page > 1 ? $page - 1 : null,
                'next_page' => $page < $totalPages ? $page + 1 : null
            ]
        ];
    }

    // Get sort clause based on sort parameter
    private function getSortClause($sort)
    {
        switch ($sort) {
            case 'oldest':
                return 'created_at ASC';
            case 'title_asc':
                return 'title ASC';
            case 'title_desc':
                return 'title DESC';
            case 'price_asc':
                return 'price ASC';
            case 'price_desc':
                return 'price DESC';
            case 'author_asc':
                return 'author ASC';
            case 'author_desc':
                return 'author DESC';
            case 'newest':
            default:
                return 'created_at DESC';
        }
    }

    // Generate pagination HTML
    public function generatePaginationHTML($pagination, $baseUrl)
    {
        if ($pagination['total_pages'] <= 1) {
            return '';
        }

        // Get current URL parameters to maintain search, category, sort when paginating
        $currentParams = $_GET;
        unset($currentParams['page']); // Remove page param as we'll set it manually

        $queryParams = http_build_query($currentParams);
        $separator = empty($queryParams) ? '?' : '&';

        $html = '<nav aria-label="Phân trang sách">';
        $html .= '<ul class="pagination justify-content-center">';

        // Previous button
        if ($pagination['has_previous']) {
            $prevUrl = $baseUrl . ($queryParams ? '?' . $queryParams . '&page=' : '?page=') . $pagination['previous_page'];
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $prevUrl . '">';
            $html .= '<i class="fas fa-chevron-left"></i> Trước';
            $html .= '</a></li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link"><i class="fas fa-chevron-left"></i> Trước</span>';
            $html .= '</li>';
        }

        // Page numbers
        $currentPage = $pagination['current_page'];
        $totalPages = $pagination['total_pages'];

        // Calculate page range to display
        $startPage = max(1, $currentPage - 2);
        $endPage = min($totalPages, $currentPage + 2);

        // Show first page if not in range
        if ($startPage > 1) {
            $firstUrl = $baseUrl . ($queryParams ? '?' . $queryParams . '&page=1' : '?page=1');
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $firstUrl . '">1</a>';
            $html .= '</li>';

            if ($startPage > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Show page numbers in range
        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i == $currentPage) {
                $html .= '<li class="page-item active">';
                $html .= '<span class="page-link">' . $i . '</span>';
                $html .= '</li>';
            } else {
                $pageUrl = $baseUrl . ($queryParams ? '?' . $queryParams . '&page=' : '?page=') . $i;
                $html .= '<li class="page-item">';
                $html .= '<a class="page-link" href="' . $pageUrl . '">' . $i . '</a>';
                $html .= '</li>';
            }
        }

        // Show last page if not in range
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            $lastUrl = $baseUrl . ($queryParams ? '?' . $queryParams . '&page=' : '?page=') . $totalPages;
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $lastUrl . '">' . $totalPages . '</a>';
            $html .= '</li>';
        }

        // Next button
        if ($pagination['has_next']) {
            $nextUrl = $baseUrl . ($queryParams ? '?' . $queryParams . '&page=' : '?page=') . $pagination['next_page'];
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $nextUrl . '">';
            $html .= 'Sau <i class="fas fa-chevron-right"></i>';
            $html .= '</a></li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link">Sau <i class="fas fa-chevron-right"></i></span>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</nav>';

        // Add pagination info
        $html .= '<div class="text-center mt-3">';
        $html .= '<small class="text-muted">';
        $html .= 'Hiển thị ' . $pagination['start'] . '-' . $pagination['end'] . ' trong tổng số ' . $pagination['total_records'] . ' cuốn sách';
        $html .= ' (Trang ' . $pagination['current_page'] . '/' . $pagination['total_pages'] . ')';
        $html .= '</small>';
        $html .= '</div>';

        return $html;
    }

    // Insert data
    public function insert($table, $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        try {
            $stmt = $this->getConnection()->prepare($sql);
            $result = $stmt->execute($data);

            if ($result) {
                $lastId = $this->getConnection()->lastInsertId();
                return $lastId ? (int) $lastId : true; // Return ID if available, or true if successful
            }

            return false;
        } catch (PDOException $exception) {
            echo "Insert error: " . $exception->getMessage();
            return false;
        }
    }

    // Update data
    public function update($table, $data, $where, $whereParams = [])
    {
        $setClause = [];
        foreach (array_keys($data) as $key) {
            $setClause[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setClause);

        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";

        try {
            $stmt = $this->getConnection()->prepare($sql);
            $params = array_merge($data, $whereParams);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $exception) {
            echo "Update error: " . $exception->getMessage();
            return false;
        }
    }

    // Delete data
    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        // var_dump($sql, $where, $params); // Debugging line to check SQL and params

        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $exception) {
            echo "Delete error: " . $exception->getMessage();
            return false;
        }
    }

    // Count records
    public function count($table, $where = '', $params = [])
    {
        $sql = "SELECT COUNT(*) as total FROM {$table}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        $result = $this->fetch($sql, $params);
        return $result ? $result['total'] : 0;
    }

    // Check if record exists
    public function exists($table, $where, $params = [])
    {
        return $this->count($table, $where, $params) > 0;
    }

    // Begin transaction
    public function beginTransaction()
    {
        try {
            if (!$this->getConnection()->inTransaction()) {
                return $this->getConnection()->beginTransaction();
            }
            return true; // Already in transaction
        } catch (PDOException $e) {
            error_log('Begin transaction error: ' . $e->getMessage());
            return false;
        }
    }

    // Commit transaction
    public function commit()
    {
        try {
            if ($this->getConnection()->inTransaction()) {
                return $this->getConnection()->commit();
            }
            return false; // No active transaction
        } catch (PDOException $e) {
            error_log('Commit transaction error: ' . $e->getMessage());
            return false;
        }
    }

    // Rollback transaction
    public function rollback()
    {
        try {
            if ($this->getConnection()->inTransaction()) {
                return $this->getConnection()->rollBack();
            }
            return false; // No active transaction
        } catch (PDOException $e) {
            error_log('Rollback transaction error: ' . $e->getMessage());
            return false;
        }
    }

    // Check if in transaction
    public function inTransaction()
    {
        try {
            return $this->getConnection()->inTransaction();
        } catch (PDOException $e) {
            error_log('Check transaction error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * User-specific methods
     */

    /**
     * Create new user
     */
    public function createUser(User $user)
    {
        $user->sanitize();
        $data = $user->toArrayForDB();
        return $this->insert('users', $data);
    }

    /**
     * Get user by ID
     */
    public function getUserById($id)
    {
        $userData = $this->fetch("SELECT * FROM users WHERE user_id = :user_id LIMIT 1", ['user_id' => $id]);
        if ($userData) {
            return new User($userData);
        }
        return false;
    }

    public function getUserOrderStats($userId)
    {
        try {
            if (!is_numeric($userId) || $userId <= 0) {
                throw new InvalidArgumentException('User ID must be a positive integer');
            }

            $stats = [
                'pending' => 0,
                'confirmed' => 0,
                'cancelled' => 0,
                'total' => 0
            ];

            // Get counts for each status
            $stats['pending'] = $this->count(
                'orders',
                'user_id = :user_id AND status = :status',
                ['user_id' => $userId, 'status' => 'pending']
            );

            $stats['confirmed'] = $this->count(
                'orders',
                'user_id = :user_id AND status = :status',
                ['user_id' => $userId, 'status' => 'confirmed']
            );

            $stats['cancelled'] = $this->count(
                'orders',
                'user_id = :user_id AND status = :status',
                ['user_id' => $userId, 'status' => 'cancelled']
            );

            $stats['total'] = $this->count('orders', 'user_id = :user_id', ['user_id' => $userId]);

            return $stats;

        } catch (Exception $e) {
            error_log('Get user order stats error: ' . $e->getMessage());
            return [
                'pending' => 0,
                'confirmed' => 0,
                'cancelled' => 0,
                'total' => 0
            ];
        }
    }

    /**
     * Get user by username or email
     */
    public function getUserByUsernameOrEmail($username)
    {
        $userData = $this->fetch(
            "SELECT * FROM users WHERE username = :username OR email = :username LIMIT 1",
            ['username' => $username]
        );

        if ($userData) {
            return new User($userData);
        }
        return false;
    }

    /**
     * Update user
     */
    public function updateUser(User $user)
    {
        $user->sanitize();
        $data = $user->toArrayForDB();
        return $this->update('users', $data, 'user_id = :user_id', ['user_id' => $user->id]);
    }

    /**
     * Delete user
     */
    public function deleteUser($id)
    {
        return $this->delete('users', 'user_id = :user_id', ['user_id' => $id]);
    }

    /**
     * Get all users
     */
    public function getAllUsers()
    {
        $usersData = $this->fetchAll("SELECT * FROM users ORDER BY name ASC");
        $users = [];
        foreach ($usersData as $userData) {
            $users[] = new User($userData);
        }
        return $users;
    }

    /**
     * Get users by permission
     */
    public function getUsersByPermission($permission)
    {
        $usersData = $this->fetchAll(
            "SELECT * FROM users WHERE permission = :permission ORDER BY name ASC",
            ['permission' => $permission]
        );
        $users = [];
        foreach ($usersData as $userData) {
            $users[] = new User($userData);
        }
        return $users;
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username, $excludeId = null)
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE username = :username";
        $params = ['username' => $username];

        if ($excludeId) {
            $query .= " AND user_id != :excludeId";
            $params['excludeId'] = $excludeId;
        }

        $result = $this->fetch($query, $params);
        return $result['count'] > 0;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null)
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE email = :email";
        $params = ['email' => $email];

        if ($excludeId) {
            $query .= " AND user_id != :excludeId";
            $params['excludeId'] = $excludeId;
        }

        $result = $this->fetch($query, $params);
        return $result['count'] > 0;
    }

    /**
     * Search users
     */
    public function searchUsers($keyword)
    {
        $searchTerm = '%' . $keyword . '%';
        $usersData = $this->fetchAll(
            "SELECT * FROM users 
             WHERE name LIKE :keyword 
                OR username LIKE :keyword 
                OR email LIKE :keyword 
                OR phone LIKE :keyword
             ORDER BY name ASC",
            ['keyword' => $searchTerm]
        );

        $users = [];
        foreach ($usersData as $userData) {
            $users[] = new User($userData);
        }
        return $users;
    }

    /**
     * Hash password with SHA256 and salt (username + password)
     */
    public function hashPassword($password, $username)
    {
        $salt = $username . $password; // Salt = username + password
        return hash('sha256', $salt);
    }

    /**
     * Verify password with SHA256 hash
     */
    public function verifyPassword($inputPassword, $hashedPassword, $username)
    {
        $inputHash = $this->hashPassword($inputPassword, $username);
        return hash_equals($hashedPassword, $inputHash);
    }

    /**
     * Login user with SHA256 password verification
     */
    public function loginUserWithSHA256($username, $password)
    {
        $userData = $this->fetch(
            "SELECT * FROM users WHERE username = :username OR email = :username LIMIT 1",
            ['username' => $username]
        );

        if ($userData) {
            // Verify password with SHA256 + salt
            if ($this->verifyPassword($password, $userData['password'], $userData['username'])) {
                return new User($userData);
            }
        }
        return false;
    }

    /**
     * Create user with SHA256 hashed password
     */
    public function createUserWithSHA256($userData)
    {
        // Hash password before saving
        if (isset($userData['password']) && isset($userData['username'])) {
            $userData['password'] = $this->hashPassword($userData['password'], $userData['username']);
        }

        return $this->insert('users', $userData);
    }

    /**
     * Update user password with SHA256
     */
    public function updateUserPassword($userId, $newPassword, $username)
    {
        $hashedPassword = $this->hashPassword($newPassword, $username);

        return $this->update(
            'users',
            ['password' => $hashedPassword],
            'user_id = :user_id',
            ['user_id' => $userId]
        );
    }

    /**
     * Authenticate user and return user data
     */
    public function authenticateUser($username, $password)
    {
        try {
            $userData = $this->fetch(
                "SELECT * FROM users WHERE (username = :username OR email = :username)",
                ['username' => $username]
            );

            if ($userData) {
                // Verify password
                if ($this->verifyPassword($password, $userData['password'], $userData['username'])) {
                    // Update last login time
                    $this->update(
                        'users',
                        ['last_login' => date('Y-m-d H:i:s')],
                        'user_id = :user_id',
                        ['user_id' => $userData['id']]
                    );

                    return $userData;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log('Authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Change user password
     */
    public function changeUserPassword($userId, $currentPassword, $newPassword)
    {
        // Get user data
        $userData = $this->fetch("SELECT * FROM users WHERE user_id = :user_id", ['user_id' => $userId]);

        if (!$userData) {
            return ['success' => false, 'message' => 'Người dùng không tồn tại'];
        }

        // Verify current password
        if (!$this->verifyPassword($currentPassword, $userData['password'], $userData['username'])) {
            return ['success' => false, 'message' => 'Mật khẩu hiện tại không đúng'];
        }

        // Update to new password
        $result = $this->updateUserPassword($userId, $newPassword, $userData['username']);

        if ($result) {
            return ['success' => true, 'message' => 'Đổi mật khẩu thành công'];
        } else {
            return ['success' => false, 'message' => 'Có lỗi xảy ra khi đổi mật khẩu'];
        }
    }

    /**
     * Reset user password (for admin)
     */
    public function resetUserPassword($userId, $newPassword)
    {
        $userData = $this->fetch("SELECT username FROM users WHERE user_id = :user_id", ['user_id' => $userId]);

        if ($userData) {
            return $this->updateUserPassword($userId, $newPassword, $userData['username']);
        }
        return false;
    }

    /**
     * Generate password hash for given username and password (utility function)
     */
    public function generatePasswordHash($username, $password)
    {
        return $this->hashPassword($password, $username);
    }

    /**
     * Validate password strength
     */
    public function validatePasswordStrength($password)
    {
        $errors = [];

        if (strlen($password) < 6) {
            $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
        }

        if (!preg_match('/[A-Za-z]/', $password)) {
            $errors[] = 'Mật khẩu phải chứa ít nhất 1 chữ cái';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Mật khẩu phải chứa ít nhất 1 số';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Cart-specific methods
     */

    /**
     * Add item to cart or update quantity if exists
     */
    public function addToCart($userId, $bookId, $quantity = 1)
    {
        try {
            // Check if item already exists in cart
            $existingItem = $this->fetch(
                "SELECT * FROM cart WHERE user_id = :user_id AND id = :id",
                ['user_id' => $userId, 'id' => $bookId]
            );

            if ($existingItem) {
                // Update quantity if item exists
                $newQuantity = $existingItem['quantity'] + $quantity;
                return $this->update(
                    'cart',
                    ['quantity' => $newQuantity],
                    'cart_id = :cart_id',
                    ['cart_id' => $existingItem['cart_id']]
                );
            } else {
                // Insert new item
                return $this->insert('cart', [
                    'user_id' => $userId,
                    'id' => $bookId,
                    'quantity' => $quantity,
                ]);
            }
        } catch (Exception $e) {
            error_log('Add to cart error: ' . $e->getMessage());
            throw $e; // Re-throw để có thể debug
        }
    }

    /**
     * Get all cart items for a specific user.
     *
     * @param int $userId The ID of the user.
     * @return array Array of cart items with book details.
     * @throws InvalidArgumentException If userId is invalid.
     * @throws RuntimeException If database query fails.
     */
    public function getCartItems(int $userId): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be a positive integer.');
        }

        try {
            return $this->fetchAll(
                "SELECT cart.cart_id, cart.user_id, cart.id, cart.quantity, 
                    books.title, books.author, books.price, books.image, books.stock
             FROM cart 
             INNER JOIN books ON cart.id = books.id 
             WHERE cart.user_id = :user_id 
             ORDER BY cart.cart_id DESC",
                ['user_id' => $userId]
            ) ?: [];
        } catch (Exception $e) {
            throw new RuntimeException('Failed to fetch cart items: ' . $e->getMessage());
        }
    }

    /**
     * Get a specific cart item by cart_id.
     *
     * @param int $cartId The ID of the cart item.
     * @return array|null Array of cart item details or null if not found.
     * @throws InvalidArgumentException If cartId is invalid.
     * @throws RuntimeException If database query fails.
     */
    public function getCartItemById(int $cartId): ?array
    {
        if ($cartId <= 0) {
            throw new InvalidArgumentException('Cart ID must be a positive integer.');
        }

        try {
            $result = $this->fetch(
                "SELECT cart.cart_id, cart.user_id, cart.id, cart.quantity, 
                    books.title, books.author, books.price, books.image, books.stock
             FROM cart 
             INNER JOIN books ON cart.id = books.id 
             WHERE cart.cart_id = :cart_id",
                ['cart_id' => $cartId]
            );

            return $result ?: null;
        } catch (Exception $e) {
            throw new RuntimeException('Failed to fetch cart item: ' . $e->getMessage());
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateCartQuantity($cartId, $quantity)
    {
        if ($quantity <= 0) {
            return $this->removeFromCart($cartId);
        }

        return $this->update(
            'cart',
            ['quantity' => $quantity],
            'cart_id = :cart_id',
            ['cart_id' => $cartId]
        );
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart($cartId)
    {
        return $this->delete('cart', 'cart_id = :cart_id', ['cart_id' => $cartId]);
    }

    /**
     * Remove specific item from user's cart
     */
    public function removeFromCartByUserAndBook($userId, $bookId)
    {
        return $this->delete(
            'cart',
            'user_id = :user_id AND book_id = :book_id',
            ['user_id' => $userId, 'book_id' => $bookId]
        );
    }

    /**
     * Clear all items from user's cart
     */
    public function clearCart($userId)
    {
        return $this->delete('cart', 'user_id = :user_id', ['user_id' => $userId]);
    }

    /**
     * Get cart item count for a user
     */
    public function getCartItemCount($userId)
    {
        $result = $this->fetch(
            "SELECT SUM(quantity) as total_items FROM cart WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        return $result ? (int) $result['total_items'] : 0;
    }

    /**
     * Get the total value of all items in a user's cart.
     *
     * @param int $userId The ID of the user.
     * @return float The total value of cart items.
     * @throws InvalidArgumentException If userId is invalid.
     * @throws RuntimeException If database query fails.
     */
    public function getCartTotal(int $userId): float
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be a positive integer.');
        }

        try {
            $result = $this->fetch(
                "SELECT SUM(cart.quantity * books.price) as total_amount 
             FROM cart 
             INNER JOIN books ON cart.id = books.id 
             WHERE cart.user_id = :user_id",
                ['user_id' => $userId]
            );

            return $result && isset($result['total_amount']) ? (float) $result['total_amount'] : 0.0;
        } catch (Exception $e) {
            throw new RuntimeException('Failed to calculate cart total: ' . $e->getMessage());
        }
    }

    /**
     * Check if book is in user's cart
     */
    public function isBookInCart($userId, $bookId)
    {
        return $this->exists(
            'cart',
            'user_id = :user_id AND book_id = :book_id',
            ['user_id' => $userId, 'book_id' => $bookId]
        );
    }

    /**
     * Get cart item quantity for specific book
     */
    public function getCartItemQuantity($userId, $bookId)
    {
        $result = $this->fetch(
            "SELECT quantity FROM cart WHERE user_id = :user_id AND book_id = :book_id",
            ['user_id' => $userId, 'book_id' => $bookId]
        );
        return $result ? (int) $result['quantity'] : 0;
    }

    /**
     * Validate cart items for a user by checking if requested quantities exceed available stock.
     *
     * @param int $userId The ID of the user.
     * @return array An array containing validation status and any errors.
     *               Format: ['valid' => bool, 'errors' => array]
     * @throws InvalidArgumentException If userId is invalid.
     * @throws RuntimeException If database query fails.
     */
    public function validateCartItems(int $userId): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be a positive integer.');
        }

        try {
            $cartItems = $this->fetchAll(
                "SELECT cart.cart_id, cart.quantity, cart.id, books.title, books.stock
             FROM cart 
             INNER JOIN books ON cart.id = books.id 
             WHERE cart.user_id = :user_id",
                ['user_id' => $userId]
            ) ?: [];

            $errors = [];
            foreach ($cartItems as $item) {
                if ($item['quantity'] > $item['stock']) {
                    $errors[] = [
                        'cart_id' => $item['cart_id'],
                        'book_id' => $item['id'],
                        'title' => $item['title'],
                        'requested' => $item['quantity'],
                        'available' => $item['stock'],
                        'message' => "Sách '{$item['title']}' chỉ còn {$item['stock']} cuốn"
                    ];
                }
            }

            return [
                'valid' => empty($errors),
                'errors' => $errors
            ];
        } catch (Exception $e) {
            throw new RuntimeException('Failed to validate cart items: ' . $e->getMessage());
        }
    }

    /**
     * Get cart summary for a user
     */
    public function getCartSummary($userId)
    {
        $items = $this->getCartItems($userId);
        $totalItems = $this->getCartItemCount($userId);
        $totalAmount = $this->getCartTotal($userId);

        return [
            'items' => $items,
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
            'item_count' => count($items)
        ];
    }

    /**
     * Merge carts (useful when user logs in and has items in session cart)
     */
    public function mergeCarts($userId, $sessionCartItems)
    {
        $this->beginTransaction();

        try {
            foreach ($sessionCartItems as $item) {
                $this->addToCart($userId, $item['book_id'], $item['quantity']);
            }

            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollback();
            error_log('Merge carts error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up cart items for books that no longer exist
     */
    public function cleanupCart()
    {
        return $this->delete(
            'cart',
            'book_id NOT IN (SELECT id FROM books)'
        );
    }

    /**
     * Order-specific methods
     */

    /**
     * Create new order with product format: bookId*quantity;bookId*quantity
     */
    public function createOrder($orderData)
    {
        try {
            return $this->insertOrder('orders', $orderData);
        } catch (Exception $e) {
            error_log('Create order error: ' . $e->getMessage());
            return false;
        }
    }

    public function updateBookStock($bookId, $quantityChange)
    {
        $sql = "UPDATE books SET stock = stock + :quantity_change WHERE id = :book_id";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([
            'quantity_change' => $quantityChange,
            'book_id' => $bookId
        ]);
    }

    public function updateVoucherUsage($voucherId)
    {
        $sql = "UPDATE vouchers SET used_count = used_count + 1 WHERE voucher_id = :voucher_id";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute(['voucher_id' => $voucherId]);
    }

    public function toggleVoucherStatus($voucherId)
    {
        try {
            if (!is_numeric($voucherId) || $voucherId <= 0) {
                throw new InvalidArgumentException('Voucher ID must be a positive integer');
            }

            // Get current voucher status
            $voucher = $this->fetch(
                "SELECT is_active FROM vouchers WHERE voucher_id = :voucher_id LIMIT 1",
                ['voucher_id' => (int) $voucherId]
            );

            if (!$voucher) {
                throw new InvalidArgumentException('Voucher does not exist');
            }

            // Toggle status: 1 -> 0, 0 -> 1
            $newStatus = $voucher['is_active'] ? 0 : 1;

            // Update voucher status
            $result = $this->update(
                'vouchers',
                ['is_active' => $newStatus],
                'voucher_id = :voucher_id',
                ['voucher_id' => (int) $voucherId]
            );

            return [
                'success' => $result > 0,
                'old_status' => $voucher['is_active'],
                'new_status' => $newStatus,
                'message' => $result > 0
                    ? ($newStatus ? 'Kích hoạt voucher thành công' : 'Vô hiệu hóa voucher thành công')
                    : 'Không thể thay đổi trạng thái voucher'
            ];

        } catch (Exception $e) {
            error_log('Toggle voucher status error: ' . $e->getMessage());
            return [
                'success' => false,
                'old_status' => null,
                'new_status' => null,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ];
        }
    }

    public function clearUserCart($userId)
    {
        $sql = "DELETE FROM cart WHERE user_id = :user_id";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Get order by ID
     */
    public function getOrderById($orderId)
    {
        try {
            if (!is_numeric($orderId) || $orderId <= 0) {
                throw new InvalidArgumentException('Order ID must be a positive integer');
            }

            return $this->fetch(
                "SELECT * FROM orders WHERE order_id = :order_id",
                ['order_id' => (int) $orderId]
            );
        } catch (Exception $e) {
            error_log('Get order by ID error: ' . $e->getMessage());
            return null;
        }
    }

    public function insertOrder($table, $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        try {
            $stmt = $this->getConnection()->prepare($sql);
            $result = $stmt->execute($data);

            if ($result) {
                $lastId = $this->getLastOrderId();
                return $lastId ? (int) $lastId : true; // Return ID if available, or true if successful
            }

            return false;
        } catch (PDOException $exception) {
            echo "Insert error: " . $exception->getMessage();
            return false;
        }
    }

    public function getLastOrderId()
    {
        try {
            $result = $this->fetch(
                "SELECT order_id FROM orders ORDER BY order_id DESC LIMIT 1"
            );

            return $result ? (int) $result['order_id'] : null;

        } catch (Exception $e) {
            error_log('Get last order ID error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update order
     */
    public function updateOrder($orderId, $orderData)
    {
        try {
            if (!is_numeric($orderId) || $orderId <= 0) {
                throw new InvalidArgumentException('Order ID must be a positive integer');
            }

            // Validate and clean data
            $allowedFields = ['user_id', 'product', 'cost', 'pay_method', 'note', 'voucher_id', 'status'];
            $cleanData = [];

            foreach ($orderData as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    switch ($field) {
                        case 'user_id':
                        case 'voucher_id':
                            if ($value !== null && !is_numeric($value)) {
                                throw new Exception("{$field} must be numeric or null");
                            }
                            $cleanData[$field] = $value !== null ? (int) $value : null;
                            break;
                        case 'cost':
                            if (!is_numeric($value)) {
                                throw new Exception("cost must be numeric");
                            }
                            $cleanData[$field] = (float) $value;
                            break;
                        case 'product':
                        case 'pay_method':
                        case 'note':
                        case 'status':
                            $cleanData[$field] = $value !== null ? trim($value) : null;
                            break;
                    }
                }
            }

            if (empty($cleanData)) {
                throw new Exception("No valid fields to update");
            }

            return $this->update(
                'orders',
                $cleanData,
                'order_id = :order_id',
                ['order_id' => (int) $orderId]
            );

        } catch (Exception $exception) {
            error_log('Update order error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    public function getOrdersByStatus($status, $page = 1, $perPage = 15)
    {
        try {
            if (!is_string($status) || empty(trim($status))) {
                throw new InvalidArgumentException('Status must be a non-empty string');
            }

            $validStatuses = ['pending', 'confirmed', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                throw new InvalidArgumentException('Invalid order status: ' . $status);
            }

            if (!is_numeric($page) || $page < 1) {
                $page = 1;
            }

            if (!is_numeric($perPage) || $perPage < 1) {
                $perPage = 15;
            }

            $offset = ($page - 1) * $perPage;

            // Fix: Sử dụng trực tiếp trong SQL thay vì parameter cho LIMIT/OFFSET
            $orders = $this->fetchAll(
                "SELECT * FROM orders 
                 WHERE status = :status 
                 ORDER BY created_at DESC 
                 LIMIT {$perPage} OFFSET {$offset}",
                ['status' => $status]
            );

            // Get total count for pagination
            $totalOrders = $this->count('orders', 'status = :status', ['status' => $status]);
            $totalPages = ceil($totalOrders / $perPage);

            return [
                'orders' => $orders ?: [],
                'pagination' => [
                    'current_page' => (int) $page,
                    'per_page' => (int) $perPage,
                    'total_records' => (int) $totalOrders,
                    'total_pages' => (int) $totalPages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages,
                    'previous_page' => $page > 1 ? $page - 1 : null,
                    'next_page' => $page < $totalPages ? $page + 1 : null
                ]
            ];

        } catch (Exception $exception) {
            error_log('Get orders by status error: ' . $exception->getMessage());
            return [
                'orders' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false,
                    'previous_page' => null,
                    'next_page' => null
                ]
            ];
        }
    }

    /**
     * Update order status (chỉ cho phép 3 trạng thái)
     */
    public function updateOrderStatus($orderId, $status)
    {
        try {
            if (!is_numeric($orderId) || $orderId <= 0) {
                throw new InvalidArgumentException('Order ID must be a positive integer');
            }

            $validStatuses = ['pending', 'confirmed', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                throw new InvalidArgumentException('Invalid order status');
            }

            return $this->update(
                'orders',
                ['status' => $status],
                'order_id = :order_id',
                ['order_id' => (int) $orderId]
            );

        } catch (Exception $exception) {
            error_log('Update order status error: ' . $exception->getMessage());
            return false;
        }
    }



    /**
     * Delete order
     */
    public function deleteOrder($orderId)
    {
        try {
            if (!is_numeric($orderId) || $orderId <= 0) {
                throw new InvalidArgumentException('Order ID must be a positive integer');
            }

            return $this->delete(
                'orders',
                'order_id = :order_id',
                ['order_id' => (int) $orderId]
            );

        } catch (Exception $e) {
            error_log('Delete order error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get orders by user ID
     */
    public function getOrdersByUserId($userId, $page = 1, $perPage = 10)
    {
        try {
            $userId = (int) $userId;
            $page = max(1, (int) $page);
            $perPage = max(1, min(100, (int) $perPage));

            $offset = ($page - 1) * $perPage;

            // Count total orders for this user using existing count method
            $totalRecords = $this->count('orders', 'user_id = :user_id', ['user_id' => $userId]);
            $totalPages = ceil($totalRecords / $perPage);

            // Get orders with pagination using existing fetchAll method
            $orders = $this->fetchAll(
                "SELECT orders.*, users.username, users.email 
             FROM orders 
             LEFT JOIN users ON orders.user_id = users.user_id 
             WHERE orders.user_id = :user_id 
             ORDER BY orders.created_at DESC 
             LIMIT {$perPage} OFFSET {$offset}",
                ['user_id' => $userId]
            );

            return [
                'orders' => $orders ?: [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages,
                    'previous_page' => $page > 1 ? $page - 1 : null,
                    'next_page' => $page < $totalPages ? $page + 1 : null
                ]
            ];

        } catch (Exception $e) {
            error_log('getOrdersByUserId error: ' . $e->getMessage());
            throw new Exception('Không thể lấy danh sách đơn hàng: ' . $e->getMessage());
        }
    }

    /**
     * Get all orders with pagination and filtering
     */
    public function getAllOrders($filters = [])
    {
        try {
            $where = '';
            $parameters = [];
            $conditions = [];

            // Build WHERE clause based on filters
            if (!empty($filters['user_id'])) {
                $conditions[] = "user_id = :user_id";
                $parameters['user_id'] = (int) $filters['user_id'];
            }

            if (!empty($filters['pay_method'])) {
                $conditions[] = "pay_method = :pay_method";
                $parameters['pay_method'] = $filters['pay_method'];
            }

            if (!empty($filters['voucher_id'])) {
                $conditions[] = "voucher_id = :voucher_id";
                $parameters['voucher_id'] = (int) $filters['voucher_id'];
            }

            if (!empty($filters['status'])) {
                $conditions[] = "status = :status";
                $parameters['status'] = $filters['status'];
            }

            if (!empty($filters['date_from'])) {
                $conditions[] = "created_at >= :date_from";
                $parameters['date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $conditions[] = "created_at <= :date_to";
                $parameters['date_to'] = $filters['date_to'];
            }

            if (!empty($filters['min_cost'])) {
                $conditions[] = "cost >= :min_cost";
                $parameters['min_cost'] = (float) $filters['min_cost'];
            }

            if (!empty($filters['max_cost'])) {
                $conditions[] = "cost <= :max_cost";
                $parameters['max_cost'] = (float) $filters['max_cost'];
            }

            if (!empty($conditions)) {
                $where = 'WHERE ' . implode(' AND ', $conditions);
            }

            $orderBy = $filters['order_by'] ?? 'created_at DESC';

            $sql = "SELECT * FROM orders {$where} ORDER BY {$orderBy}";

            $orders = $this->fetchAll($sql, $parameters);

            return $orders ?: [];

        } catch (Exception $exception) {
            error_log('Get all orders error: ' . $exception->getMessage());
            return [];
        }
    }

    /**
     * Check if order belongs to user
     */
    public function isOrderOwnedByUser($orderId, $userId)
    {
        try {
            if (!is_numeric($orderId) || $orderId <= 0) {
                return false;
            }
            if (!is_numeric($userId) || $userId <= 0) {
                return false;
            }

            return $this->exists(
                'orders',
                'order_id = :order_id AND user_id = :user_id',
                [
                    'order_id' => (int) $orderId,
                    'user_id' => (int) $userId
                ]
            );
        } catch (Exception $e) {
            error_log('Check order ownership error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse product string to get book items
     * Format: bookId*quantity;bookId*quantity
     */
    public function parseProductString($productString)
    {
        try {
            if (empty($productString)) {
                return [];
            }

            $items = [];
            // Split by comma first
            $parts = explode(',', trim($productString));

            foreach ($parts as $part) {
                $part = trim($part);

                // Use regex to match pattern: number (xnumber)
                // Pattern explanation: (\d+) captures book ID, \s* matches spaces, \(x(\d+)\) captures quantity
                if (preg_match('/^(\d+)\s*\(x(\d+)\)$/', $part, $matches)) {
                    $bookId = (int) $matches[1];
                    $quantity = (int) $matches[2];

                    if ($bookId > 0 && $quantity > 0) {
                        $items[] = [
                            'book_id' => $bookId,
                            'quantity' => $quantity
                        ];
                    }
                }
            }

            return $items;
        } catch (Exception $e) {
            error_log('Parse product string error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Build product string from book items
     * Format: bookId*quantity;bookId*quantity
     */
    public function buildProductString($items)
    {
        try {
            if (empty($items) || !is_array($items)) {
                return '';
            }

            $productParts = [];
            foreach ($items as $item) {
                if (isset($item['book_id']) && isset($item['quantity'])) {
                    $bookId = (int) $item['book_id'];
                    $quantity = (int) $item['quantity'];

                    if ($bookId > 0 && $quantity > 0) {
                        $productParts[] = "{$bookId}*{$quantity}";
                    }
                }
            }

            return implode(';', $productParts);
        } catch (Exception $e) {
            error_log('Build product string error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get order details with book information
     */
    public function getOrderWithBooks($orderId)
    {
        try {
            // Validate input
            if (!is_numeric($orderId) || $orderId <= 0) {
                throw new InvalidArgumentException('Order ID must be a positive integer');
            }

            // Get order data
            $order = $this->getOrderById($orderId);
            if (!$order) {
                return null;
            }

            // Parse product string to get book items
            $items = $this->parseProductString($order['product']);

            if (empty($items)) {
                $order['items'] = [];
                return $order;
            }

            // Get book details for each item
            $orderItems = [];
            foreach ($items as $item) {
                $book = $this->fetch(
                    "SELECT id, title, image, price, author, stock FROM books WHERE id = :id",
                    ['id' => $item['book_id']]
                );

                if ($book) {
                    $orderItems[] = [
                        'book_id' => $item['book_id'], // Fixed: was $item['id']
                        'quantity' => $item['quantity'],
                        'book_title' => $book['title'],
                        'book_author' => $book['author'],
                        'book_price' => (float) $book['price'],
                        'book_image' => $book['image'],
                        'book_stock' => (int) $book['stock'],
                        'total_price' => (float) $book['price'] * (int) $item['quantity']
                    ];
                } else {
                    // Handle case where book doesn't exist anymore
                    $orderItems[] = [
                        'book_id' => $item['book_id'],
                        'quantity' => $item['quantity'],
                        'book_title' => 'Sách không tồn tại',
                        'book_author' => 'N/A',
                        'book_price' => 0,
                        'book_image' => null,
                        'book_stock' => 0,
                        'total_price' => 0,
                        'is_deleted' => true
                    ];
                }
            }

            $order['items'] = $orderItems;
            $order['total_items'] = count($orderItems);
            $order['total_books'] = array_sum(array_column($orderItems, 'quantity'));

            return $order;

        } catch (Exception $e) {
            error_log('Get order with books error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get orders by payment method
     */
    public function getOrdersByPaymentMethod($payMethod, $page = 1, $perPage = 15)
    {
        try {
            return $this->getAllOrders($page, $perPage, ['pay_method' => $payMethod]);
        } catch (Exception $e) {
            error_log('Get orders by payment method error: ' . $e->getMessage());
            return [
                'orders' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false
                ]
            ];
        }
    }

    /**
     * Get orders by voucher ID
     */
    public function getOrdersByVoucherId($voucherId, $page = 1, $perPage = 15)
    {
        try {
            if (!is_numeric($voucherId) || $voucherId <= 0) {
                throw new InvalidArgumentException('Voucher ID must be a positive integer');
            }

            return $this->getAllOrders($page, $perPage, ['voucher_id' => (int) $voucherId]);
        } catch (Exception $e) {
            error_log('Get orders by voucher ID error: ' . $e->getMessage());
            return [
                'orders' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false
                ]
            ];
        }
    }

    /**
     * Get order statistics
     */
    public function getOrderStatistics($dateFrom = null, $dateTo = null)
    {
        try {
            $where = '';
            $params = [];

            if ($dateFrom && $dateTo) {
                $where = 'WHERE created_at BETWEEN :date_from AND :date_to';
                $params = ['date_from' => $dateFrom, 'date_to' => $dateTo];
            } elseif ($dateFrom) {
                $where = 'WHERE created_at >= :date_from';
                $params = ['date_from' => $dateFrom];
            } elseif ($dateTo) {
                $where = 'WHERE created_at <= :date_to';
                $params = ['date_to' => $dateTo];
            }

            $stats = $this->fetch(
                "SELECT 
                COUNT(*) as total_orders,
                SUM(cost) as total_revenue,
                AVG(cost) as average_order_value,
                MIN(cost) as min_order_value,
                MAX(cost) as max_order_value,
                COUNT(DISTINCT user_id) as unique_customers,
                COUNT(CASE WHEN voucher_id IS NOT NULL THEN 1 END) as orders_with_voucher
             FROM orders {$where}",
                $params
            );

            // Get payment method breakdown
            $paymentMethods = $this->fetchAll(
                "SELECT 
                pay_method,
                COUNT(*) as count,
                SUM(cost) as total_amount
             FROM orders {$where}
             GROUP BY pay_method
             ORDER BY count DESC",
                $params
            );

            $stats['payment_methods'] = $paymentMethods;

            return $stats;
        } catch (Exception $e) {
            error_log('Get order statistics error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get recent orders
     */
    public function getRecentOrders($limit = 10)
    {
        try {
            if (!is_numeric($limit) || $limit <= 0) {
                $limit = 10;
            }

            return $this->fetchAll(
                "SELECT o.*, u.username 
             FROM orders o 
             LEFT JOIN users u ON o.user_id = u.user_id 
             ORDER BY o.created_at DESC 
             LIMIT :limit",
                ['limit' => (int) $limit]
            );
        } catch (Exception $e) {
            error_log('Get recent orders error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search orders
     */
    public function searchOrders($keyword, $page = 1, $perPage = 15)
    {
        try {
            if (empty($keyword)) {
                return $this->getAllOrders($page, $perPage);
            }

            $searchTerm = '%' . $keyword . '%';
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT orders.*, users.username 
                FROM orders orders 
                LEFT JOIN users users ON orders.user_id = users.user_id 
                WHERE orders.order_id LIKE :search 
                   OR orders.product LIKE :search 
                   OR orders.pay_method LIKE :search 
                   OR orders.note LIKE :search 
                   OR orders.status LIKE :search
                   OR users.username LIKE :search
                ORDER BY orders.created_at DESC 
                LIMIT :limit OFFSET :offset";

            $parameters = [
                'search' => $searchTerm,
                'limit' => $perPage,
                'offset' => $offset
            ];

            $orders = $this->fetchAll($sql, $parameters);

            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as count 
                     FROM orders orders 
                     LEFT JOIN users users ON orders.user_id = users.user_id 
                     WHERE orders.order_id LIKE :search 
                        OR orders.product LIKE :search 
                        OR orders.pay_method LIKE :search 
                        OR orders.note LIKE :search 
                        OR orders.status LIKE :search
                        OR users.username LIKE :search";

            $totalOrders = $this->fetch($countSql, ['search' => $searchTerm])['count'];
            $totalPages = ceil($totalOrders / $perPage);

            return [
                'orders' => $orders,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_records' => $totalOrders,
                    'total_pages' => $totalPages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ];

        } catch (Exception $exception) {
            error_log('Search orders error: ' . $exception->getMessage());
            return [
                'orders' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false
                ]
            ];
        }
    }

    /**
     * Get order status options
     */
    public function getOrderStatusOptions()
    {
        return [
            'pending' => 'Chờ xử lý',
            'confirmed' => 'Đã xác nhận',
            'cancelled' => 'Đã hủy'
        ];
    }

    /**
     * Get order status display name
     */
    public function getOrderStatusDisplayName($status)
    {
        $statusOptions = $this->getOrderStatusOptions();
        return $statusOptions[$status] ?? $status;
    }

    /**
     * Check if order status can be changed
     */
    public function canChangeOrderStatus($currentStatus, $newStatus)
    {
        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['cancelled'],
            'cancelled' => []
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }

    /**
     * Get available vouchers for order
     * Returns vouchers that are active, not expired, and haven't reached usage limit
     */
    public function getAvailableVouchersForOrder($orderAmount = 0, $userId = null)
    {
        try {
            $currentDate = date('Y-m-d H:i:s');
            $where = [];
            $params = [];

            // Base conditions for active vouchers
            $where[] = "is_active = 'active'";
            $where[] = "created_at <= :expires_at";
            $where[] = "expires_at >= :current_date";
            $where[] = "(usage_limit IS NULL OR used_count < usage_limit)";

            $params['current_date'] = $currentDate;

            // Filter by minimum order amount if specified
            if ($orderAmount > 0) {
                $where[] = "(min_order_amount IS NULL OR min_order_amount <= :order_amount)";
                $params['order_amount'] = (float) $orderAmount;
            }

            $whereClause = implode(' AND ', $where);

            $vouchers = $this->fetchAll(
                "SELECT 
                    voucher_id,
                    code,
                    name,
                    description,
                    min_order_amount,
                    discount_percent,
                    quantity,
                    used_count,
                    is_active,
                    created_at,
                    expires_at
                 FROM vouchers 
                 WHERE {$whereClause}
                 ORDER BY created_at DESC",
                array_merge($params, ['order_amount_sort' => max($orderAmount, 1)])
            );

            // Calculate actual discount for each voucher
            foreach ($vouchers as &$voucher) {
                $voucher['calculated_discount'] = $this->calculateVoucherDiscount(
                    $voucher,
                    $orderAmount
                );

                // Add remaining usage info
                if ($voucher['usage_limit']) {
                    $voucher['remaining_uses'] = max(0, $voucher['usage_limit'] - $voucher['used_count']);
                } else {
                    $voucher['remaining_uses'] = null; // Unlimited
                }

                // Format dates for display
                $voucher['start_date_formatted'] = date('d/m/Y H:i', strtotime($voucher['start_date']));
                $voucher['end_date_formatted'] = date('d/m/Y H:i', strtotime($voucher['end_date']));

                // Calculate days until expiry
                $endDate = new DateTime($voucher['end_date']);
                $today = new DateTime();
                $voucher['days_until_expiry'] = $endDate->diff($today)->days;
                if ($endDate < $today) {
                    $voucher['days_until_expiry'] = 0;
                }
            }

            return $vouchers;

        } catch (Exception $e) {
            error_log('Get available vouchers error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate discount amount for a specific voucher and order amount
     */
    public function calculateVoucherDiscount($voucher, $orderAmount)
    {
        try {
            if ($orderAmount <= 0) {
                return 0;
            }

            // Check minimum order amount requirement
            if ($voucher['min_order_amount'] && $orderAmount < $voucher['min_order_amount']) {
                return 0;
            }

            $discountAmount = 0;

            if ($voucher['discount_type'] === 'percentage') {
                // Percentage discount
                $discountAmount = ($orderAmount * $voucher['discount_value']) / 100;

                // Apply maximum discount limit if set
                if ($voucher['max_discount_amount'] && $discountAmount > $voucher['max_discount_amount']) {
                    $discountAmount = $voucher['max_discount_amount'];
                }
            } else {
                // Fixed amount discount
                $discountAmount = $voucher['discount_value'];
            }

            // Ensure discount doesn't exceed order amount
            $discountAmount = min($discountAmount, $orderAmount);

            return round($discountAmount, 2);

        } catch (Exception $e) {
            error_log('Calculate voucher discount error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if a specific voucher can be applied to an order
     */
    public function canApplyVoucher($voucherCode, $orderAmount, $userId = null)
    {
        try {
            $voucher = $this->fetch(
                "SELECT * FROM vouchers WHERE code = :code LIMIT 1",
                ['code' => $voucherCode]
            );

            if (!$voucher) {
                return [
                    'valid' => false,
                    'message' => 'Mã voucher không tồn tại',
                    'voucher' => null
                ];
            }

            $currentDate = date('Y-m-d H:i:s');

            // Check if voucher is active
            if (!$voucher['is_active']) {
                return [
                    'valid' => false,
                    'message' => 'Voucher đã bị vô hiệu hóa',
                    'voucher' => $voucher
                ];
            }

            // Check if voucher has expired
            if ($voucher['expires_at'] && $voucher['expires_at'] < $currentDate) {
                return [
                    'valid' => false,
                    'message' => 'Voucher đã hết hạn',
                    'voucher' => $voucher
                ];
            }

            // Check usage limit
            if ($voucher['used_count'] >= $voucher['quantity']) {
                return [
                    'valid' => false,
                    'message' => 'Voucher đã hết lượt sử dụng',
                    'voucher' => $voucher
                ];
            }

            // Check minimum order amount
            if ($voucher['min_order_amount'] && $orderAmount < $voucher['min_order_amount']) {
                $minAmount = number_format($voucher['min_order_amount'] * 1000, 0, ',', '.');
                return [
                    'valid' => false,
                    'message' => "Đơn hàng tối thiểu {$minAmount} VNĐ để sử dụng voucher này",
                    'voucher' => $voucher
                ];
            }

            // Calculate discount amount
            $discountAmount = ($orderAmount * $voucher['discount_percent']) / 100;

            return [
                'valid' => true,
                'message' => 'Voucher hợp lệ',
                'voucher' => $voucher,
                'discount_amount' => $discountAmount
            ];

        } catch (Exception $e) {
            error_log('Can apply voucher error: ' . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Có lỗi xảy ra khi kiểm tra voucher',
                'voucher' => null
            ];
        }
    }

    /**
     * Get voucher by code
     */
    public function getVoucherByCode($code)
    {
        try {
            return $this->fetch(
                "SELECT * FROM vouchers WHERE code = :code LIMIT 1",
                ['code' => $code]
            );
        } catch (Exception $e) {
            error_log('Get voucher by code error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get voucher by ID
     */
    public function getVoucherById($voucherId)
    {
        try {
            if (!is_numeric($voucherId) || $voucherId <= 0) {
                return null;
            }

            return $this->fetch(
                "SELECT * FROM vouchers WHERE voucher_id = :voucher_id LIMIT 1",
                ['voucher_id' => (int) $voucherId]
            );
        } catch (Exception $e) {
            error_log('Get voucher by ID error: ' . $e->getMessage());
            return null;
        }
    }

    public function getAllActiveVouchers($orderAmount = 0)
    {
        try {
            $currentDate = date('Y-m-d H:i:s');
            $where = [];
            $params = [];

            // Base conditions for active vouchers
            $where[] = "is_active = 1";
            $where[] = "expires_at >= :current_date";
            $where[] = "used_count < quantity";

            $params['current_date'] = $currentDate;

            // Filter by minimum order amount if specified
            if ($orderAmount > 0) {
                $where[] = "min_order_amount <= :order_amount";
                $params['order_amount'] = (float) $orderAmount;
            }

            $whereClause = implode(' AND ', $where);

            $vouchers = $this->fetchAll(
                "SELECT 
                voucher_id,
                code,
                name,
                description,
                min_order_amount,
                discount_percent,
                quantity,
                used_count,
                is_active,
                created_at,
                expires_at
             FROM vouchers 
             WHERE {$whereClause}
             ORDER BY discount_percent DESC, min_order_amount ASC",
                $params
            );

            return $vouchers ?: [];

        } catch (Exception $e) {
            error_log('Get all active vouchers error: ' . $e->getMessage());
            return [];
        }
    }
    public function voucherCodeExists($code)
    {
        try {
            if (empty($code)) {
                return false;
            }

            $query = "SELECT COUNT(*) as count FROM vouchers WHERE code = :code";
            $params = ['code' => trim($code)];

            $result = $this->fetch($query, $params);
            return $result && $result['count'] > 0;

        } catch (Exception $e) {
            error_log('Voucher code exists check error: ' . $e->getMessage());
            return false;
        }
    }
    public function createVoucher($voucherData)
    {
        try {
            // Insert voucher into database
            $result = $this->insert('vouchers', $voucherData);

            return $result;

        } catch (Exception $e) {
            error_log('Create voucher error: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteVoucher($voucherId)
    {
        try {
            // Insert voucher into database
            $result = $this->deleteVoucherById('vouchers', $voucherId);

            return $result;

        } catch (Exception $e) {
            error_log('Delete voucher error: ' . $e->getMessage());
            return false;
        }
    }
    public function deleteVoucherById($table, $where, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE voucher_id = {$where}";// Debugging line to check SQL and params

        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $exception) {
            echo "Delete error: " . $exception->getMessage();
            return false;
        }
    }

    /**
     * Update voucher information
     */
    public function updateVoucher($voucherId, $voucherData)
    {
        try {
            if (!is_numeric($voucherId) || $voucherId <= 0) {
                throw new InvalidArgumentException('Voucher ID must be a positive integer');
            }

            // Validate and clean data
            $allowedFields = [
                'code',
                'name',
                'description',
                'min_order_amount',
                'discount_percent',
                'quantity',
                'is_active',
                'expires_at'
            ];
            $cleanData = [];

            foreach ($voucherData as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    switch ($field) {
                        case 'min_order_amount':
                        case 'discount_percent':
                            if ($value !== null && !is_numeric($value)) {
                                throw new Exception("{$field} must be numeric or null");
                            }
                            $cleanData[$field] = $value !== null ? (float) $value : null;
                            break;
                        case 'quantity':
                            if (!is_numeric($value) || $value < 0) {
                                throw new Exception("quantity must be a non-negative number");
                            }
                            $cleanData[$field] = (int) $value;
                            break;
                        case 'is_active':
                            // Handle boolean or string values
                            if (is_bool($value)) {
                                $cleanData[$field] = $value ? 1 : 0;
                            } elseif (in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                                $cleanData[$field] = in_array($value, [1, '1', 'true']) ? 1 : 0;
                            } else {
                                throw new Exception("is_active must be boolean or 0/1");
                            }
                            break;
                        case 'expires_at':
                            if ($value !== null) {
                                // Validate date format
                                $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
                                if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
                                    // Try alternative format Y-m-d
                                    $date = DateTime::createFromFormat('Y-m-d', $value);
                                    if ($date) {
                                        $cleanData[$field] = $date->format('Y-m-d 23:59:59');
                                    } else {
                                        throw new Exception("expires_at must be in Y-m-d H:i:s or Y-m-d format");
                                    }
                                } else {
                                    $cleanData[$field] = $value;
                                }
                            } else {
                                $cleanData[$field] = null;
                            }
                            break;
                        case 'code':
                        case 'name':
                        case 'description':
                            $cleanData[$field] = $value !== null ? trim($value) : null;
                            break;
                    }
                }
            }

            if (empty($cleanData)) {
                throw new Exception("No valid fields to update");
            }

            // Check if code is being updated and if it already exists
            if (isset($cleanData['code'])) {
                $existingVoucher = $this->fetch(
                    "SELECT voucher_id FROM vouchers WHERE code = :code AND voucher_id != :voucher_id LIMIT 1",
                    ['code' => $cleanData['code'], 'voucher_id' => $voucherId]
                );

                if ($existingVoucher) {
                    throw new Exception("Mã voucher '{$cleanData['code']}' đã tồn tại");
                }
            }

            // Validate business rules
            if (isset($cleanData['discount_percent'])) {
                if ($cleanData['discount_percent'] < 0 || $cleanData['discount_percent'] > 100) {
                    throw new Exception("Phần trăm giảm giá phải từ 0 đến 100");
                }
            }

            if (isset($cleanData['min_order_amount']) && $cleanData['min_order_amount'] < 0) {
                throw new Exception("Số tiền đơn hàng tối thiểu không được âm");
            }

            if (isset($cleanData['expires_at']) && $cleanData['expires_at']) {
                $expiryDate = new DateTime($cleanData['expires_at']);
                $now = new DateTime();
                if ($expiryDate <= $now) {
                    throw new Exception("Ngày hết hạn phải lớn hơn thời gian hiện tại");
                }
            }

            // Update voucher
            $result = $this->update(
                'vouchers',
                $cleanData,
                'voucher_id = :voucher_id',
                ['voucher_id' => (int) $voucherId]
            );

            return $result;

        } catch (Exception $e) {
            error_log('Update voucher error: ' . $e->getMessage());
            throw $e; // Re-throw để admin.php có thể catch và hiển thị lỗi
        }
    }

    /**
     * Comments-specific methods
     */

    /**
     * Add new comment
     */
    public function addComment($commentData)
    {
        try {
            // Validate required fields
            $requiredFields = ['user_id', 'id', 'content'];
            foreach ($requiredFields as $field) {
                if (!isset($commentData[$field]) || empty($commentData[$field])) {
                    throw new InvalidArgumentException("Field {$field} is required");
                }
            }

            // Validate and clean data
            $cleanData = [];
            $allowedFields = ['user_id', 'id', 'content', 'image', 'vote'];

            foreach ($commentData as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    switch ($field) {
                        case 'user_id':
                        case 'id':
                            if (!is_numeric($value) || $value <= 0) {
                                throw new InvalidArgumentException("{$field} must be a positive integer");
                            }
                            $cleanData[$field] = (int) $value;
                            break;
                        case 'vote':
                            if ($value !== null && !is_numeric($value)) {
                                throw new InvalidArgumentException("vote must be numeric or null");
                            }
                            $cleanData[$field] = $value !== null ? (int) $value : 0;
                            break;
                        case 'content':
                            if (empty(trim($value))) {
                                throw new InvalidArgumentException("content cannot be empty");
                            }
                            $cleanData[$field] = trim($value);
                            break;
                        case 'image':
                            $cleanData[$field] = $value ? trim($value) : null;
                            break;
                    }
                }
            }

            // Add create_at timestamp
            $cleanData['create_at'] = date('Y-m-d H:i:s');

            // Verify user exists
            if (!$this->exists('users', 'user_id = :user_id', ['user_id' => $cleanData['user_id']])) {
                throw new InvalidArgumentException("User does not exist");
            }

            // Verify book exists
            if (!$this->exists('books', 'id = :id', ['id' => $cleanData['id']])) {
                throw new InvalidArgumentException("Book does not exist");
            }

            return $this->insert('comments', $cleanData);

        } catch (Exception $e) {
            error_log('Add comment error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update comment
     */
    public function updateComment($commentId, $commentData)
    {
        try {
            if (!is_numeric($commentId) || $commentId <= 0) {
                throw new InvalidArgumentException('Comment ID must be a positive integer');
            }

            // Check if comment exists
            if (!$this->exists('comments', 'cmt_id = :cmt_id', ['cmt_id' => $commentId])) {
                throw new InvalidArgumentException("Comment does not exist");
            }

            // Validate and clean data
            $cleanData = [];
            $allowedFields = ['content', 'image', 'vote'];

            foreach ($commentData as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    switch ($field) {
                        case 'vote':
                            if ($value !== null && !is_numeric($value)) {
                                throw new InvalidArgumentException("vote must be numeric or null");
                            }
                            $cleanData[$field] = $value !== null ? (int) $value : 0;
                            break;
                        case 'content':
                            if ($value !== null) {
                                if (empty(trim($value))) {
                                    throw new InvalidArgumentException("content cannot be empty");
                                }
                                $cleanData[$field] = trim($value);
                            }
                            break;
                        case 'image':
                            $cleanData[$field] = $value ? trim($value) : null;
                            break;
                    }
                }
            }

            if (empty($cleanData)) {
                throw new InvalidArgumentException("No valid fields to update");
            }

            return $this->update(
                'comments',
                $cleanData,
                'cmt_id = :cmt_id',
                ['cmt_id' => (int) $commentId]
            );

        } catch (Exception $e) {
            error_log('Update comment error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remove comment
     */
    public function removeComment($commentId)
    {
        try {
            if (!is_numeric($commentId) || $commentId <= 0) {
                throw new InvalidArgumentException('Comment ID must be a positive integer');
            }

            // Check if comment exists
            if (!$this->exists('comments', 'cmt_id = :cmt_id', ['cmt_id' => $commentId])) {
                throw new InvalidArgumentException("Comment does not exist");
            }

            return $this->delete(
                'comments',
                'cmt_id = :cmt_id',
                ['cmt_id' => (int) $commentId]
            );

        } catch (Exception $e) {
            error_log('Remove comment error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get comment by ID
     */
    public function getCommentById($commentId)
    {
        try {
            if (!is_numeric($commentId) || $commentId <= 0) {
                throw new InvalidArgumentException('Comment ID must be a positive integer');
            }

            $result = $this->fetch(
                "SELECT c.*, u.username, u.name as user_name, b.title as book_title 
                 FROM comments c 
                 LEFT JOIN users u ON c.user_id = u.user_id 
                 LEFT JOIN books b ON c.id = b.id 
                 WHERE c.cmt_id = :cmt_id 
                 LIMIT 1",
                ['cmt_id' => (int) $commentId]
            );

            return $result ?: null;

        } catch (Exception $e) {
            error_log('Get comment by ID error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get vote by comment ID (alias for getCommentById focusing on vote)
     */
    public function getVoteById($commentId)
    {
        try {
            if (!is_numeric($commentId) || $commentId <= 0) {
                throw new InvalidArgumentException('Comment ID must be a positive integer');
            }

            $result = $this->fetch(
                "SELECT cmt_id, vote, user_id, id FROM comments WHERE cmt_id = :cmt_id LIMIT 1",
                ['cmt_id' => (int) $commentId]
            );

            return $result ?: null;

        } catch (Exception $e) {
            error_log('Get vote by ID error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get comments by book ID with pagination
     */
    public function getCommentsByBookId($bookId, $page = 1, $perPage = 10, $orderBy = 'create_at DESC')
    {
        try {
            if (!is_numeric($bookId) || $bookId <= 0) {
                throw new InvalidArgumentException('Book ID must be a positive integer');
            }

            $offset = ($page - 1) * $perPage;

            $comments = $this->fetchAll(
                "SELECT c.*, u.username, u.name as user_name 
                 FROM comments c 
                 LEFT JOIN users u ON c.user_id = u.user_id 
                 WHERE c.id = :book_id 
                 ORDER BY {$orderBy}
                 LIMIT {$perPage} OFFSET {$offset}",
                ['book_id' => (int) $bookId]
            );

            // Get total count for pagination
            $totalComments = $this->count('comments', 'id = :book_id', ['book_id' => (int) $bookId]);
            $totalPages = ceil($totalComments / $perPage);

            return [
                'comments' => $comments ?: [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_records' => $totalComments,
                    'total_pages' => $totalPages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ];

        } catch (Exception $e) {
            error_log('Get comments by book ID error: ' . $e->getMessage());
            return [
                'comments' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false
                ]
            ];
        }
    }

    /**
     * Get comments by user ID
     */
    public function getCommentsByUserId($userId, $page = 1, $perPage = 10)
    {
        try {
            if (!is_numeric($userId) || $userId <= 0) {
                throw new InvalidArgumentException('User ID must be a positive integer');
            }

            $offset = ($page - 1) * $perPage;

            $comments = $this->fetchAll(
                "SELECT c.*, b.title as book_title 
                 FROM comments c 
                 LEFT JOIN books b ON c.id = b.id 
                 WHERE c.user_id = :user_id 
                 ORDER BY c.create_at DESC
                 LIMIT {$perPage} OFFSET {$offset}",
                ['user_id' => (int) $userId]
            );

            // Get total count
            $totalComments = $this->count('comments', 'user_id = :user_id', ['user_id' => (int) $userId]);
            $totalPages = ceil($totalComments / $perPage);

            return [
                'comments' => $comments ?: [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_records' => $totalComments,
                    'total_pages' => $totalPages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ];

        } catch (Exception $e) {
            error_log('Get comments by user ID error: ' . $e->getMessage());
            return [
                'comments' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false
                ]
            ];
        }
    }

    /**
     * Update comment vote
     */
    public function updateCommentVote($commentId, $vote)
    {
        try {
            if (!is_numeric($commentId) || $commentId <= 0) {
                throw new InvalidArgumentException('Comment ID must be a positive integer');
            }

            if (!is_numeric($vote)) {
                throw new InvalidArgumentException('Vote must be numeric');
            }

            $vote = (int) $vote;

            // Validate vote range (assuming 1-5 rating system)
            if ($vote < 1 || $vote > 5) {
                throw new InvalidArgumentException('Vote must be between 1 and 5');
            }

            return $this->update(
                'comments',
                ['vote' => $vote],
                'cmt_id = :cmt_id',
                ['cmt_id' => (int) $commentId]
            );

        } catch (Exception $e) {
            error_log('Update comment vote error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get average vote for a book
     */
    public function getBookAverageVote($bookId)
    {
        try {
            if (!is_numeric($bookId) || $bookId <= 0) {
                throw new InvalidArgumentException('Book ID must be a positive integer');
            }

            $result = $this->fetch(
                "SELECT 
                    AVG(vote) as average_vote,
                    COUNT(vote) as total_votes
                 FROM comments 
                 WHERE id = :book_id AND vote IS NOT NULL AND vote > 0",
                ['book_id' => (int) $bookId]
            );

            return [
                'average_vote' => $result['average_vote'] ? round((float) $result['average_vote'], 1) : 0,
                'total_votes' => (int) $result['total_votes']
            ];

        } catch (Exception $e) {
            error_log('Get book average vote error: ' . $e->getMessage());
            return [
                'average_vote' => 0,
                'total_votes' => 0
            ];
        }
    }

    /**
     * Check if user has already commented on a book
     */
    public function hasUserCommentedOnBook($userId, $bookId)
    {
        try {
            if (!is_numeric($userId) || $userId <= 0) {
                return false;
            }
            if (!is_numeric($bookId) || $bookId <= 0) {
                return false;
            }

            return $this->exists(
                'comments',
                'user_id = :user_id AND id = :book_id',
                ['user_id' => (int) $userId, 'book_id' => (int) $bookId]
            );

        } catch (Exception $e) {
            error_log('Check user comment exists error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent comments with pagination
     */
    public function getRecentComments($limit = 10)
    {
        try {
            if (!is_numeric($limit) || $limit <= 0) {
                $limit = 10;
            }

            return $this->fetchAll(
                "SELECT c.*, u.username, u.name as user_name, b.title as book_title 
                 FROM comments c 
                 LEFT JOIN users u ON c.user_id = u.user_id 
                 LEFT JOIN books b ON c.id = b.id 
                 ORDER BY c.create_at DESC 
                 LIMIT :limit",
                ['limit' => (int) $limit]
            ) ?: [];

        } catch (Exception $e) {
            error_log('Get recent comments error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete all comments by user
     */
    public function deleteCommentsByUserId($userId)
    {
        try {
            if (!is_numeric($userId) || $userId <= 0) {
                throw new InvalidArgumentException('User ID must be a positive integer');
            }

            return $this->delete(
                'comments',
                'user_id = :user_id',
                ['user_id' => (int) $userId]
            );

        } catch (Exception $e) {
            error_log('Delete comments by user ID error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete all comments for a book
     */
    public function deleteCommentsByBookId($bookId)
    {
        try {
            if (!is_numeric($bookId) || $bookId <= 0) {
                throw new InvalidArgumentException('Book ID must be a positive integer');
            }

            return $this->delete(
                'comments',
                'id = :book_id',
                ['book_id' => (int) $bookId]
            );

        } catch (Exception $e) {
            error_log('Delete comments by book ID error: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Check if voucher is expired
     */
    public function isVoucherExpired($voucherId)
    {
        $sql = "SELECT expires_at FROM vouchers WHERE id = :id";
        $voucher = $this->fetch($sql, ['id' => $voucherId]);

        if (!$voucher) {
            return true; // Consider non-existent voucher as expired
        }

        return strtotime($voucher['expires_at']) <= time();
    }

    /**
     * Get vouchers expiring soon (within next 7 days)
     */
    public function getVouchersExpiringSoon($days = 7)
    {
        $sql = "SELECT * FROM vouchers 
            WHERE is_active = 1 
            AND expires_at > NOW() 
            AND expires_at <= DATE_ADD(NOW(), INTERVAL :days DAY)
            AND (quantity - used_count) > 0 
            ORDER BY expires_at ASC";

        return $this->fetchAll($sql, ['days' => $days]);
    }

    /**
     * Clean up expired vouchers (mark as inactive)
     */
    public function cleanupExpiredVouchers()
    {
        $sql = "UPDATE vouchers 
            SET is_active = 0 
            WHERE expires_at <= NOW() 
            AND is_active = 1";

        return $this->query($sql);
    }
}
?>