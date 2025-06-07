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
            $stmt->execute($data);
            return $this->getConnection()->lastInsertId();
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
        return $this->getConnection()->beginTransaction();
    }

    // Commit transaction
    public function commit()
    {
        return $this->getConnection()->commit();
    }

    // Rollback transaction
    public function rollback()
    {
        return $this->getConnection()->rollBack();
    }

    // Close connection
    public function close()
    {
        $this->conn = null;
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
     * Create new order
     */
    public function createOrder($orderData)
    {
        try {
            $result = $this->insert('orders', $orderData);
            return $result ? $this->conn->lastInsertId() : false;
        } catch (Exception $e) {
            error_log('Create order error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update voucher usage count
     */
    public function updateVoucherUsage($voucherCode)
    {
        $configFile = __DIR__ . '/../config/vouchers.json';
        if (file_exists($configFile)) {
            $vouchers = json_decode(file_get_contents($configFile), true);
            if (isset($vouchers[$voucherCode])) {
                $vouchers[$voucherCode]['used_count']++;
                file_put_contents($configFile, json_encode($vouchers, JSON_PRETTY_PRINT));
                return true;
            }
        }
        return false;
    }

}
?>