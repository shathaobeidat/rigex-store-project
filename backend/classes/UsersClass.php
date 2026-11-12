<?php
require_once __DIR__ . '/../config/db.php';

class UsersClass
{

    public function register($name, $email, $password, $role) {
        $dbh = DB_conn::getConnection();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $inst = $dbh->prepare("INSERT INTO users (name, email, password_hash, role, address_line1) VALUES (?, ?, ?, ?, '')");
        return $inst->execute([$name, $email, $hashedPassword, $role]);
    }

    public function login($email, $password) {
        try {
            $dbh = DB_conn::getConnection();
            $stmt = $dbh->prepare(
                "SELECT * FROM users
                 WHERE email = ? AND deleted_at IS NULL"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password_hash'])) {
                return $user;
            }
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }

    
    public function getAllUsers(): array
    {
        $dbh = DB_conn::getConnection();

        $sql = "SELECT u.id, u.name, u.email, u.role, u.created_at,
                       COALESCE(p.orders_count, 0) AS orders_count,
                       COALESCE(p.total_spent, 0)  AS total_spent
                FROM users u
                LEFT JOIN (
                    SELECT o.user_id,
                           COUNT(o.id)       AS orders_count,
                           SUM(o.total_jod)  AS total_spent
                    FROM orders o
                    WHERE o.deleted_at IS NULL
                      AND o.status <> 'cancelled'
                    GROUP BY o.user_id
                ) p ON p.user_id = u.id
                WHERE u.deleted_at IS NULL
                ORDER BY u.created_at DESC, u.id DESC";

        $rows = $dbh->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['role']         = ucfirst($row['role']);
            $row['orders_count'] = (int)$row['orders_count'];
            $row['total_spent']  = (float)$row['total_spent'];
            $row['created_at']   = substr($row['created_at'], 0, 10);
        }
        unset($row);

        return $rows;
    }

    public function getUserById(int $id)
    {
        $dbh  = DB_conn::getConnection();
        $stmt = $dbh->prepare(
            "SELECT id, name, email, role, created_at
             FROM users WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function emailExists(string $email, int $excludeId = 0): bool
    {
        $dbh  = DB_conn::getConnection();
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?");
        $stmt->execute([$email, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getStats(): array
    {
        $dbh = DB_conn::getConnection();

        $c = $dbh->query(
            "SELECT COUNT(*)                                                AS total,
                    SUM(created_at >= DATE_FORMAT(NOW(), '%Y-%m-01'))       AS new_month
             FROM users
             WHERE role = 'customer' AND deleted_at IS NULL"
        )->fetch(PDO::FETCH_ASSOC);

        $r = $dbh->query(
            "SELECT COALESCE(SUM(total_jod), 0) AS total,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
                                      THEN total_jod END), 0) AS this_month,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')
                                       AND created_at <  DATE_FORMAT(NOW(), '%Y-%m-01')
                                      THEN total_jod END), 0) AS last_month
             FROM orders
             WHERE status <> 'cancelled' AND deleted_at IS NULL"
        )->fetch(PDO::FETCH_ASSOC);

        $total  = (int)$c['total'];
        $this_m = (float)$r['this_month'];
        $last_m = (float)$r['last_month'];

        return [
            'total_customers'  => $total,
            'active_customers' => $total,
            'new_this_month'   => (int)$c['new_month'],
            'revenue_total'    => (float)$r['total'],
            'revenue_growth'   => $last_m > 0 ? round(($this_m - $last_m) / $last_m * 100, 1) : 0,
        ];
    }


    public function addUser(string $name, string $email, string $password, string $role): bool
    {
        $dbh  = DB_conn::getConnection();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $dbh->prepare(
            "INSERT INTO users (name, email, password_hash, role, address_line1)
             VALUES (?, ?, ?, ?, '')"
        );
        return $stmt->execute([$name, $email, $hash, strtolower($role)]);
    }

  
    public function updateUser(int $id, string $name, string $email, string $role, string $password = ''): bool
    {
        $dbh = DB_conn::getConnection();

        if ($password !== '') {
            $stmt = $dbh->prepare(
                "UPDATE users
                 SET name = ?, email = ?, role = ?, password_hash = ?
                 WHERE id = ? AND deleted_at IS NULL"
            );
            return $stmt->execute([
                $name, $email, strtolower($role), password_hash($password, PASSWORD_DEFAULT), $id
            ]);
        }

        $stmt = $dbh->prepare(
            "UPDATE users
             SET name = ?, email = ?, role = ?
             WHERE id = ? AND deleted_at IS NULL"
        );
        return $stmt->execute([$name, $email, strtolower($role), $id]);
    }

  
    public function deleteUser(int $id): bool
    {
        $dbh  = DB_conn::getConnection();
        $stmt = $dbh->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }
    
   
    public function getProfile(int $id)
    {
        $dbh  = DB_conn::getConnection();
        $stmt = $dbh->prepare(
            "SELECT u.id, u.name, u.email, u.role, u.address_line1, u.created_at,
                    (SELECT COUNT(*) FROM orders o
                      WHERE o.user_id = u.id
                        AND o.deleted_at IS NULL
                        AND o.status <> 'cancelled')                       AS orders_count,
                    (SELECT COALESCE(SUM(o.total_jod), 0) FROM orders o
                      WHERE o.user_id = u.id
                        AND o.deleted_at IS NULL
                        AND o.status <> 'cancelled')                       AS total_spent
             FROM users u
             WHERE u.id = ? AND u.deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $row['orders_count'] = (int)$row['orders_count'];
        $row['total_spent']  = (float)$row['total_spent'];
        return $row;
    }

    
    public function updateProfile(int $id, string $name, string $email, string $address): bool
    {
        $dbh  = DB_conn::getConnection();
        $stmt = $dbh->prepare(
            "UPDATE users
             SET name = ?, email = ?, address_line1 = ?
             WHERE id = ? AND deleted_at IS NULL"
        );
        return $stmt->execute([$name, $email, $address, $id]);
    }

    public function changePassword(int $id, string $currentPassword, string $newPassword): bool
    {
        $dbh  = DB_conn::getConnection();

        $stmt = $dbh->prepare("SELECT password_hash FROM users WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($currentPassword, $hash)) {
            return false;
        }

        $upd = $dbh->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND deleted_at IS NULL");
        return $upd->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
    }

    
    public function getUserOrders(int $userId, int $limit = 10): array
    {
        $dbh  = DB_conn::getConnection();
        $stmt = $dbh->prepare(
            "SELECT id, status, total_jod, created_at
             FROM orders
             WHERE user_id = :uid AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['id']         = (int)$r['id'];
            $r['total_jod']  = (float)$r['total_jod'];
            $r['status']     = ucfirst((string)$r['status']);
            $r['created_at'] = substr((string)$r['created_at'], 0, 10);
        }
        unset($r);

        return $rows;
    }
}