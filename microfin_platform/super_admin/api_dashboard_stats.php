<?php
session_start();

if (!isset($_SESSION['super_admin_logged_in']) || $_SESSION['super_admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../backend/db_connect.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {

        // ============================================================
        // DEFAULT: Dashboard stats + chart data (polled every 5s)
        // ============================================================
        case 'dashboard':
            $data = [];

            // Stat cards
            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
            $data['active_tenants'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'Active' AND deleted_at IS NULL");
            $data['active_users'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE user_type = 'Super Admin' AND status = 'Active' AND deleted_at IS NULL");
            $data['active_super_admin_accounts'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'Inactive' AND deleted_at IS NULL");
            $data['inactive_users'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            $stmt = $pdo->query("SELECT COALESCE(SUM(mrr), 0) AS total_mrr FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
            $data['total_mrr'] = number_format((float) $stmt->fetch(PDO::FETCH_ASSOC)['total_mrr'], 2);

            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE status IN ('Demo Requested', 'Contacted') AND deleted_at IS NULL");
            $data['pending_applications'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            // Chart: User growth (last 12 months)
            $stmt = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count
                FROM users WHERE deleted_at IS NULL
                GROUP BY month ORDER BY month ASC
                LIMIT 12
            ");
            $data['user_growth_chart'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Chart: Tenant activity by status (precise monthly breakdown)
            $stmt = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN status IN ('Draft', 'CONSIDER', 'Demo Requested', 'Contacted', 'Accepted', 'Rejected') THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN status NOT IN ('Active', 'Draft', 'CONSIDER', 'Demo Requested', 'Contacted', 'Accepted', 'Rejected') THEN 1 ELSE 0 END) AS inactive_count
                FROM tenants
                WHERE deleted_at IS NULL
                GROUP BY month ORDER BY month ASC
                LIMIT 12
            ");
            $data['tenant_activity_chart'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Chart: Sales trends (payment totals by month)
            $stmt = $pdo->query("
                SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month, COALESCE(SUM(payment_amount), 0) AS total
                FROM payments WHERE payment_status IN ('Posted', 'Verified')
                GROUP BY month ORDER BY month ASC
                LIMIT 12
            ");
            $data['sales_trends_chart'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($data);
            break;

        // ============================================================
        // REPORTS: Privacy-safe tenant activity summary
        // ============================================================
        case 'reports':
            $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $date_to = $_GET['date_to'] ?? date('Y-m-d');
            $tenant_id = $_GET['tenant_id'] ?? '';
            $data = [];

            // Tenant Activity Report (application-stage tenants excluded)
            $sql = "
                SELECT t.tenant_id, t.tenant_name, t.status, t.plan_tier, t.created_at,
                    CASE
                        WHEN t.status = 'Active' THEN 'Active'
                        WHEN t.status IN ('Draft', 'CONSIDER', 'Demo Requested', 'Contacted', 'Accepted', 'Rejected') THEN 'Pending Application'
                        ELSE 'Inactive'
                    END AS status_legend
                FROM tenants t
                WHERE t.deleted_at IS NULL
                  AND t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                  AND t.status NOT IN ('Draft', 'CONSIDER', 'Demo Requested', 'Contacted', 'Accepted', 'Rejected')
            ";
            $params = [$date_from, $date_to];
            if ($tenant_id !== '') {
                $sql .= " AND t.tenant_id = ?";
                $params[] = $tenant_id;
            }
            $sql .= " ORDER BY t.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data['tenant_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($data);
            break;

        // ============================================================
        // SALES: Revenue, top tenants, transactions
        // ============================================================
        case 'sales':
            $period = $_GET['period'] ?? 'monthly';
            $date_from = $_GET['date_from'] ?? date('Y-m-01');
            $date_to = $_GET['date_to'] ?? date('Y-m-d');
            $data = [];

            // Total revenue
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(payment_amount), 0) AS total_revenue,
                       COUNT(*) AS total_transactions
                FROM payments
                WHERE payment_status IN ('Posted', 'Verified')
                AND payment_date BETWEEN ? AND ?
            ");
            $stmt->execute([$date_from, $date_to]);
            $rev = $stmt->fetch(PDO::FETCH_ASSOC);
            $data['total_revenue'] = number_format((float) $rev['total_revenue'], 2);
            $data['total_transactions'] = (int) $rev['total_transactions'];
            $data['avg_transaction'] = $rev['total_transactions'] > 0
                ? number_format((float) $rev['total_revenue'] / $rev['total_transactions'], 2)
                : '0.00';

            // Top performing tenants
            $stmt = $pdo->prepare("
                SELECT t.tenant_name, t.plan_tier,
                    COALESCE(SUM(p.payment_amount), 0) AS total_sales,
                    COUNT(p.payment_id) AS transaction_count
                FROM tenants t
                LEFT JOIN payments p ON p.tenant_id = t.tenant_id
                    AND p.payment_status IN ('Posted', 'Verified')
                    AND p.payment_date BETWEEN ? AND ?
                WHERE t.status = 'Active' AND t.deleted_at IS NULL
                GROUP BY t.tenant_id
                ORDER BY total_sales DESC
                LIMIT 5
            ");
            $stmt->execute([$date_from, $date_to]);
            $data['top_tenants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Revenue chart by period
            $date_format = '%Y-%m';
            if ($period === 'daily') $date_format = '%Y-%m-%d';
            elseif ($period === 'weekly') $date_format = '%x-W%v';

            $stmt = $pdo->prepare("
                SELECT DATE_FORMAT(payment_date, ?) AS period_label,
                       COALESCE(SUM(payment_amount), 0) AS total
                FROM payments
                WHERE payment_status IN ('Posted', 'Verified')
                AND payment_date BETWEEN ? AND ?
                GROUP BY period_label
                ORDER BY period_label ASC
            ");
            $stmt->execute([$date_format, $date_from, $date_to]);
            $data['revenue_chart'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Recent transactions
            $stmt = $pdo->prepare("
                SELECT p.payment_reference, t.tenant_name, p.payment_amount,
                       p.payment_method, p.payment_status, p.payment_date
                FROM payments p
                LEFT JOIN tenants t ON p.tenant_id = t.tenant_id
                WHERE p.payment_date BETWEEN ? AND ?
                ORDER BY p.payment_date DESC
                LIMIT 50
            ");
            $stmt->execute([$date_from, $date_to]);
            $data['transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($data);
            break;

        // ============================================================
        // AUDIT LOGS: Filtered audit trail
        // ============================================================
        case 'audit_logs':
            $action_type = $_GET['action_type'] ?? '';
            $tenant_id = $_GET['tenant_id'] ?? '';
            $date_from = $_GET['date_from'] ?? '';
            $date_to = $_GET['date_to'] ?? '';

            $sql = "
                SELECT al.log_id, al.action_type, al.entity_type, al.entity_id,
                       al.description, al.ip_address, al.created_at,
                       u.username, u.email AS user_email,
                       t.tenant_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                LEFT JOIN tenants t ON al.tenant_id = t.tenant_id
                WHERE 1=1
            ";
            $params = [];

            if ($action_type !== '') {
                $sql .= " AND al.action_type = ?";
                $params[] = $action_type;
            }
            if ($tenant_id !== '') {
                $sql .= " AND al.tenant_id = ?";
                $params[] = $tenant_id;
            }
            if ($date_from !== '') {
                $sql .= " AND al.created_at >= ?";
                $params[] = $date_from;
            }
            if ($date_to !== '') {
                $sql .= " AND al.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
                $params[] = $date_to;
            }

            $sql .= " ORDER BY al.log_id DESC LIMIT 200";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

