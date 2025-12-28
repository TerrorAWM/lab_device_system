<?php
/**
 * 导师学生管理 API
 * 
 * GET /api/student.php - 获取导师名下学生列表
 * POST /api/student.php - 添加单个学生
 * POST /api/student.php?action=import - 批量导入学生(Excel/CSV)
 * POST /api/student.php?action=remove - 移除学生绑定
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$user = requireAuth();

// 验证是否为教师
if ($user['user_type'] !== 'teacher') {
    respForbidden('仅限教师访问此接口');
}

$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        getStudentList($user);
        break;
    case 'POST':
        switch ($action) {
            case 'import':
                importStudents($user);
                break;
            case 'remove':
                removeStudent($user);
                break;
            default:
                addStudent($user);
                break;
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取导师名下学生列表
 */
function getStudentList(array $user): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('
        SELECT u.user_id, u.username, u.real_name, u.phone, u.created_at, u.status,
               s.student_no, s.major, s.college
        FROM t_user u
        JOIN t_user_student s ON u.user_id = s.user_id
        WHERE s.advisor_id = ?
        ORDER BY u.created_at DESC
    ');
    $stmt->execute([$user['user_id']]);
    $students = $stmt->fetchAll();

    $items = array_map(function($s) {
        return [
            'user_id' => $s['user_id'],
            'username' => $s['username'],
            'real_name' => $s['real_name'],
            'student_no' => $s['student_no'],
            'major' => $s['major'],
            'college' => $s['college'],
            'phone' => $s['phone'],
            'status' => $s['status'],
            'created_at' => $s['created_at']
        ];
    }, $students);

    respOK([
        'items' => $items,
        'total' => count($items)
    ]);
}

/**
 * 添加单个学生
 */
function addStudent(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $studentNo = trim($input['student_no'] ?? '');
    $realName = trim($input['real_name'] ?? '');
    $major = trim($input['major'] ?? '');
    $college = trim($input['college'] ?? '');
    $phone = trim($input['phone'] ?? '');

    if (empty($studentNo)) {
        respError('学号不能为空');
    }

    if (empty($realName)) {
        respError('姓名不能为空');
    }

    $pdo = getDB();

    // 检查学号是否已存在
    $stmt = $pdo->prepare('SELECT user_id FROM t_user_student WHERE student_no = ?');
    $stmt->execute([$studentNo]);
    if ($stmt->fetch()) {
        respError('该学号已被注册');
    }

    try {
        $pdo->beginTransaction();

        // 创建用户账号，默认密码123456，需修改密码标记
        $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);
        $username = $studentNo; // 使用学号作为用户名

        $stmt = $pdo->prepare('
            INSERT INTO t_user (username, password, real_name, role, user_type, phone, need_change_pwd)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ');
        $stmt->execute([$username, $defaultPassword, $realName, 'user', 'student', $phone ?: null]);
        $userId = (int)$pdo->lastInsertId();

        // 创建学生扩展信息
        $stmt = $pdo->prepare('
            INSERT INTO t_user_student (user_id, student_no, major, college, advisor_id)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$userId, $studentNo, $major ?: null, $college ?: null, $user['user_id']]);

        $pdo->commit();

        respOK([
            'user_id' => $userId,
            'username' => $username,
            'student_no' => $studentNo,
            'real_name' => $realName
        ], '学生添加成功，默认密码为123456');

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('添加失败：' . $e->getMessage());
    }
}

/**
 * 批量导入学生 (支持Excel/CSV)
 * 
 * 上传文件格式：CSV或制表符分隔的文本
 * 列顺序：学号, 姓名, 专业, 学院, 手机号
 */
function importStudents(array $user): void
{
    // 处理JSON格式的批量导入
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        importFromJson($user);
        return;
    }

    // 处理文件上传
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        respError('请上传文件');
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls'])) {
        respError('仅支持 CSV、TXT、XLSX、XLS 格式');
    }

    $content = file_get_contents($file['tmp_name']);
    
    // 处理Excel文件 (简单的CSV/TSV解析)
    if (in_array($ext, ['xlsx', 'xls'])) {
        // 对于真正的Excel文件，需要使用PhpSpreadsheet库
        // 这里提供一个简单的处理方式：要求用户导出为CSV
        respError('请将Excel文件另存为CSV格式后重新上传');
    }

    // 解析CSV/TXT
    $lines = preg_split('/\r\n|\r|\n/', $content);
    $students = [];
    $errors = [];
    $lineNum = 0;

    foreach ($lines as $line) {
        $lineNum++;
        $line = trim($line);
        if (empty($line)) continue;

        // 跳过表头（如果第一行包含"学号"字样）
        if ($lineNum === 1 && (strpos($line, '学号') !== false || strpos($line, 'student') !== false)) {
            continue;
        }

        // 尝试不同的分隔符
        if (strpos($line, "\t") !== false) {
            $fields = explode("\t", $line);
        } elseif (strpos($line, ',') !== false) {
            $fields = str_getcsv($line);
        } else {
            $errors[] = "第{$lineNum}行：无法解析格式";
            continue;
        }

        if (count($fields) < 2) {
            $errors[] = "第{$lineNum}行：字段不足（至少需要学号和姓名）";
            continue;
        }

        $students[] = [
            'student_no' => trim($fields[0] ?? ''),
            'real_name' => trim($fields[1] ?? ''),
            'major' => trim($fields[2] ?? ''),
            'college' => trim($fields[3] ?? ''),
            'phone' => trim($fields[4] ?? '')
        ];
    }

    if (empty($students)) {
        respError('文件中没有有效的学生数据');
    }

    // 批量导入
    $result = batchImportStudents($user, $students);
    $result['parse_errors'] = $errors;

    respOK($result, "导入完成：成功 {$result['success']} 人，失败 {$result['failed']} 人");
}

/**
 * JSON格式批量导入
 */
function importFromJson(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['students']) || !is_array($input['students'])) {
        respError('请提供学生列表');
    }

    $result = batchImportStudents($user, $input['students']);

    respOK($result, "导入完成：成功 {$result['success']} 人，失败 {$result['failed']} 人");
}

/**
 * 批量导入学生核心逻辑
 */
function batchImportStudents(array $advisor, array $students): array
{
    $pdo = getDB();
    $success = 0;
    $failed = 0;
    $errors = [];
    $imported = [];

    $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);

    foreach ($students as $index => $student) {
        $studentNo = trim($student['student_no'] ?? '');
        $realName = trim($student['real_name'] ?? '');
        $major = trim($student['major'] ?? '');
        $college = trim($student['college'] ?? '');
        $phone = trim($student['phone'] ?? '');

        // 验证必填字段
        if (empty($studentNo)) {
            $failed++;
            $errors[] = "第" . ($index + 1) . "条：学号为空";
            continue;
        }

        if (empty($realName)) {
            $failed++;
            $errors[] = "第" . ($index + 1) . "条：姓名为空";
            continue;
        }

        try {
            // 检查学号是否已存在
            $stmt = $pdo->prepare('SELECT s.user_id, s.advisor_id FROM t_user_student s WHERE s.student_no = ?');
            $stmt->execute([$studentNo]);
            $existing = $stmt->fetch();

            if ($existing) {
                // 学号已存在，检查是否已有导师
                if ($existing['advisor_id']) {
                    if ($existing['advisor_id'] == $advisor['user_id']) {
                        $errors[] = "第" . ($index + 1) . "条：{$studentNo} 已在您名下";
                    } else {
                        $errors[] = "第" . ($index + 1) . "条：{$studentNo} 已绑定其他导师";
                    }
                    $failed++;
                    continue;
                } else {
                    // 更新导师绑定
                    $stmt = $pdo->prepare('UPDATE t_user_student SET advisor_id = ? WHERE user_id = ?');
                    $stmt->execute([$advisor['user_id'], $existing['user_id']]);
                    $success++;
                    $imported[] = ['student_no' => $studentNo, 'real_name' => $realName, 'action' => 'bound'];
                    continue;
                }
            }

            // 创建新学生
            $pdo->beginTransaction();

            $username = $studentNo;
            $stmt = $pdo->prepare('
                INSERT INTO t_user (username, password, real_name, role, user_type, phone, need_change_pwd)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ');
            $stmt->execute([$username, $defaultPassword, $realName, 'user', 'student', $phone ?: null]);
            $userId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('
                INSERT INTO t_user_student (user_id, student_no, major, college, advisor_id)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([$userId, $studentNo, $major ?: null, $college ?: null, $advisor['user_id']]);

            $pdo->commit();

            $success++;
            $imported[] = ['student_no' => $studentNo, 'real_name' => $realName, 'action' => 'created'];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $failed++;
            $errors[] = "第" . ($index + 1) . "条：{$studentNo} 导入失败 - " . $e->getMessage();
        }
    }

    return [
        'success' => $success,
        'failed' => $failed,
        'total' => count($students),
        'imported' => $imported,
        'errors' => $errors
    ];
}

/**
 * 移除学生绑定
 */
function removeStudent(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $userId = (int)($input['user_id'] ?? 0);

    if (!$userId) {
        respError('学生ID不能为空');
    }

    $pdo = getDB();

    // 验证学生是否属于该导师
    $stmt = $pdo->prepare('SELECT * FROM t_user_student WHERE user_id = ? AND advisor_id = ?');
    $stmt->execute([$userId, $user['user_id']]);
    if (!$stmt->fetch()) {
        respError('该学生不在您的名下');
    }

    // 移除导师绑定（不删除学生账号）
    $stmt = $pdo->prepare('UPDATE t_user_student SET advisor_id = NULL WHERE user_id = ?');
    $stmt->execute([$userId]);

    respOK(null, '已解除与该学生的绑定关系');
}
