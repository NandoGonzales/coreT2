<?php
// loan_process_action.php — AJAX backend for loan process
if (session_status() == PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
header('Content-Type: application/json; charset=utf-8');

$user_id   = $_SESSION['userdata']['user_id'] ?? 0;
$user_name = $_SESSION['userdata']['full_name'] ?? 'Unknown';
$action    = $_POST['action'] ?? $_GET['action'] ?? '';

try {

    // GET: Applications list
    if ($action === 'get_applications') {
        $status = $_GET['status'] ?? '';
        $search = '%' . trim($_GET['search'] ?? '') . '%';
        $where  = "WHERE 1=1";
        $params = []; $types = '';
        if ($status) { $where .= " AND la.status=?"; $params[] = $status; $types .= 's'; }
        if (trim($_GET['search'] ?? '')) {
            $where .= " AND (m.full_name LIKE ? OR la.app_code LIKE ? OR la.loan_type LIKE ?)";
            $params[] = $search; $params[] = $search; $params[] = $search; $types .= 'sss';
        }
        $sql  = "SELECT la.*, m.full_name, m.contact_no, m.member_code,
                        u.full_name AS created_by_name,
                        ci.result AS ci_result, ci.ci_id,
                        ci_u.full_name AS ci_officer_name
                 FROM loan_applications la
                 LEFT JOIN members m ON m.member_id = la.member_id
                 LEFT JOIN users u ON u.user_id = la.created_by
                 LEFT JOIN credit_investigations ci ON ci.app_id = la.app_id
                 LEFT JOIN users ci_u ON ci_u.user_id = ci.assigned_to
                 $where ORDER BY la.created_at DESC";
        $stmt = $conn->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $cr = $conn->query("SELECT status, COUNT(*) as cnt FROM loan_applications GROUP BY status");
        $counts = [];
        while ($r = $cr->fetch_assoc()) $counts[$r['status']] = (int)$r['cnt'];
        echo json_encode(['success'=>true,'data'=>$rows,'counts'=>$counts]);
        exit();
    }

    // GET: Single detail
    if ($action === 'get_detail') {
        $app_id = intval($_GET['app_id'] ?? 0);
        $stmt = $conn->prepare("SELECT la.*, m.full_name, m.contact_no, m.address, m.member_code,
                   m.member_credit_score, m.risk_tier,
                   u.full_name AS created_by_name, ov.full_name AS override_by_name
            FROM loan_applications la
            LEFT JOIN members m ON m.member_id = la.member_id
            LEFT JOIN users u ON u.user_id = la.created_by
            LEFT JOIN users ov ON ov.user_id = la.override_by
            WHERE la.app_id = ?");
        $stmt->bind_param('i',$app_id); $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$app) { echo json_encode(['success'=>false,'error'=>'Not found']); exit(); }
        $cs = $conn->prepare("SELECT ci.*, u.full_name AS assigned_to_name, ab.full_name AS assigned_by_name
            FROM credit_investigations ci
            LEFT JOIN users u ON u.user_id = ci.assigned_to
            LEFT JOIN users ab ON ab.user_id = ci.assigned_by
            WHERE ci.app_id = ? ORDER BY ci.ci_id DESC LIMIT 1");
        $cs->bind_param('i',$app_id); $cs->execute();
        $ci = $cs->get_result()->fetch_assoc(); $cs->close();
        echo json_encode(['success'=>true,'app'=>$app,'ci'=>$ci]);
        exit();
    }

    // GET: Members
    if ($action === 'get_members') {
        $res = $conn->query("SELECT member_id, full_name, member_code, contact_no, member_credit_score, risk_tier FROM members WHERE status='Active' ORDER BY full_name ASC");
        echo json_encode(['success'=>true,'members'=>$res->fetch_all(MYSQLI_ASSOC)]);
        exit();
    }

    // GET: Users
    if ($action === 'get_users') {
        $res = $conn->query("SELECT user_id, full_name, role FROM users WHERE status='Active' ORDER BY full_name ASC");
        echo json_encode(['success'=>true,'users'=>$res->fetch_all(MYSQLI_ASSOC)]);
        exit();
    }

    // POST: Submit application
    if ($action === 'submit_application') {
        $member_id  = intval($_POST['member_id'] ?? 0);
        $loan_type  = trim($_POST['loan_type'] ?? '');
        $amount     = floatval($_POST['principal_amount'] ?? 0);
        $rate       = floatval($_POST['interest_rate'] ?? 0);
        $term       = intval($_POST['loan_term'] ?? 0);
        $purpose    = trim($_POST['purpose'] ?? '');
        $collateral = trim($_POST['collateral'] ?? '');
        if (!$member_id||!$loan_type||!$amount||!$term) { echo json_encode(['success'=>false,'error'=>'Kumpletuhin ang required fields.']); exit(); }
        $app_code = 'APP-' . strtoupper(substr(uniqid(),-6)) . '-' . date('ymd');
        $stmt = $conn->prepare("INSERT INTO loan_applications (app_code,member_id,loan_type,principal_amount,interest_rate,loan_term,purpose,collateral,status,created_by) VALUES (?,?,?,?,?,?,?,?,'Pending',?)");
        $stmt->bind_param('sisddiisi',$app_code,$member_id,$loan_type,$amount,$rate,$term,$purpose,$collateral,$user_id);
        $stmt->execute(); $new_id = $conn->insert_id; $stmt->close();
        _audit($conn,$user_id,'Loan Application Submitted','Loan Process',$new_id,"App $app_code — ₱".number_format($amount,2));
        echo json_encode(['success'=>true,'app_id'=>$new_id,'app_code'=>$app_code,'message'=>"Application $app_code submitted!"]);
        exit();
    }

    // POST: Assign CI
    if ($action === 'assign_ci') {
        $app_id = intval($_POST['app_id'] ?? 0);
        $assigned_to = intval($_POST['assigned_to'] ?? 0);
        $chk = $conn->prepare("SELECT ci_id FROM credit_investigations WHERE app_id=?");
        $chk->bind_param('i',$app_id); $chk->execute();
        $existing = $chk->get_result()->fetch_assoc(); $chk->close();
        if ($existing) {
            $s = $conn->prepare("UPDATE credit_investigations SET assigned_to=?,assigned_by=?,assigned_at=NOW(),result='Pending' WHERE app_id=?");
            $s->bind_param('iii',$assigned_to,$user_id,$app_id);
        } else {
            $s = $conn->prepare("INSERT INTO credit_investigations (app_id,assigned_to,assigned_by,assigned_at) VALUES (?,?,?,NOW())");
            $s->bind_param('iii',$app_id,$assigned_to,$user_id);
        }
        $s->execute(); $s->close();
        $u = $conn->prepare("UPDATE loan_applications SET status='CI In Progress',updated_at=NOW() WHERE app_id=?");
        $u->bind_param('i',$app_id); $u->execute(); $u->close();
        _audit($conn,$user_id,'CI Assigned','Loan Process',$app_id,"User $assigned_to");
        echo json_encode(['success'=>true,'message'=>'CI officer assigned!']);
        exit();
    }

    // POST: CI Feedback
    if ($action === 'submit_ci') {
        $app_id     = intval($_POST['app_id'] ?? 0);
        $background = trim($_POST['background_check'] ?? 'Pending');
        $capacity   = trim($_POST['capacity_to_pay'] ?? 'Pending');
        $character  = trim($_POST['character_check'] ?? 'Pending');
        $feedback   = trim($_POST['ci_feedback'] ?? '');
        $result     = trim($_POST['result'] ?? '');
        if (!in_array($result,['Passed','For Review','Failed'])) {
            if ($background==='Failed'||$capacity==='Poor'||$character==='Poor') $result='Failed';
            elseif ($background==='Passed'&&$capacity==='Good'&&$character==='Good') $result='Passed';
            else $result='For Review';
        }
        $u = $conn->prepare("UPDATE credit_investigations SET background_check=?,capacity_to_pay=?,character_check=?,ci_feedback=?,result=?,completed_at=NOW() WHERE app_id=?");
        $u->bind_param('sssssi',$background,$capacity,$character,$feedback,$result,$app_id); $u->execute(); $u->close();
        $st = match($result){'Passed'=>'CI Passed','For Review'=>'CI For Review','Failed'=>'CI Failed',default=>'CI In Progress'};
        $u2 = $conn->prepare("UPDATE loan_applications SET status=?,updated_at=NOW() WHERE app_id=?");
        $u2->bind_param('si',$st,$app_id); $u2->execute(); $u2->close();
        _audit($conn,$user_id,"CI Completed: $result",'Loan Process',$app_id,$feedback);
        echo json_encode(['success'=>true,'result'=>$result,'message'=>"CI result: $result"]);
        exit();
    }

    // POST: Run AI Evaluation
    if ($action === 'run_evaluation') {
        $app_id = intval($_POST['app_id'] ?? 0);
        $s = $conn->prepare("SELECT member_id,principal_amount FROM loan_applications WHERE app_id=?");
        $s->bind_param('i',$app_id); $s->execute();
        $app = $s->get_result()->fetch_assoc(); $s->close();
        if (!$app) { echo json_encode(['success'=>false,'error'=>'Not found']); exit(); }
        require_once(__DIR__ . '/../ai_credit_scoring_function.php');
        $res = calculate_ai_credit_score($app['member_id']);
        if (!$res['success']) { echo json_encode(['success'=>false,'error'=>$res['error']??'AI failed']); exit(); }
        $ai_score = $res['credit_score'];
        $ai_risk  = $res['risk_category'];
        $suggested = _suggested($ai_score, (float)$app['principal_amount']);
        $u = $conn->prepare("UPDATE loan_applications SET ai_credit_score=?,ai_risk_category=?,final_score=?,final_risk_category=?,suggested_amount=?,status='Evaluated',updated_at=NOW() WHERE app_id=?");
        $u->bind_param('isssdi',$ai_score,$ai_risk,$ai_score,$ai_risk,$suggested,$app_id); $u->execute(); $u->close();
        $conn->query("UPDATE members SET member_credit_score=$ai_score,risk_tier='$ai_risk',last_score_update=NOW() WHERE member_id={$app['member_id']}");
        _audit($conn,$user_id,'AI Evaluation','Loan Process',$app_id,"Score:$ai_score $ai_risk");
        echo json_encode(['success'=>true,'ai_score'=>$ai_score,'ai_risk'=>$ai_risk,'suggested_amount'=>$suggested,'message'=>"Score: $ai_score ($ai_risk)"]);
        exit();
    }

    // POST: Manual Override
    if ($action === 'manual_override') {
        $app_id       = intval($_POST['app_id'] ?? 0);
        $manual_score = intval($_POST['manual_score'] ?? 0);
        $reason       = trim($_POST['override_reason'] ?? '');
        if ($manual_score<0||$manual_score>100) { echo json_encode(['success'=>false,'error'=>'Score: 0-100 lang.']); exit(); }
        if (!$reason) { echo json_encode(['success'=>false,'error'=>'Kailangan ng reason.']); exit(); }
        $manual_risk = _risk($manual_score);
        $u = $conn->prepare("UPDATE loan_applications SET manual_score=?,manual_risk_category=?,final_score=?,final_risk_category=?,override_reason=?,override_by=?,override_at=NOW(),status='Evaluated',updated_at=NOW() WHERE app_id=?");
        $u->bind_param('iisssii',$manual_score,$manual_risk,$manual_score,$manual_risk,$reason,$user_id,$app_id); $u->execute(); $u->close();
        _audit($conn,$user_id,'Manual Score Override','Loan Process',$app_id,"Score:$manual_score ($manual_risk) Reason:$reason By:$user_name");
        echo json_encode(['success'=>true,'manual_score'=>$manual_score,'manual_risk'=>$manual_risk,'message'=>"Overridden to $manual_score ($manual_risk)"]);
        exit();
    }

    // POST: Take Action
    if ($action === 'take_action') {
        $app_id   = intval($_POST['app_id'] ?? 0);
        $decision = trim($_POST['decision'] ?? '');
        $notes    = trim($_POST['notes'] ?? '');
        $amount   = floatval($_POST['approved_amount'] ?? 0);
        if (!in_array($decision,['Approved','Rejected','Pending'])) { echo json_encode(['success'=>false,'error'=>'Invalid decision']); exit(); }
        $s = $conn->prepare("SELECT * FROM loan_applications WHERE app_id=?");
        $s->bind_param('i',$app_id); $s->execute();
        $app = $s->get_result()->fetch_assoc(); $s->close();
        if (!$app) { echo json_encode(['success'=>false,'error'=>'Not found']); exit(); }
        $u = $conn->prepare("UPDATE loan_applications SET status=?,action_by=?,action_notes=?,action_at=NOW(),approved_amount=?,updated_at=NOW() WHERE app_id=?");
        $u->bind_param('sisdi',$decision,$user_id,$notes,$amount,$app_id); $u->execute(); $u->close();
        $loan_id = null;
        if ($decision === 'Approved') {
            $final_amount = $amount ?: $app['principal_amount'];
            $loan_code    = 'LN-' . str_pad($app_id+1000,5,'0',STR_PAD_LEFT);
            $final_score  = $app['final_score'] ?? $app['ai_credit_score'] ?? 0;
            $final_risk   = $app['final_risk_category'] ?? $app['ai_risk_category'] ?? 'Fair';
            $ins = $conn->prepare("INSERT INTO loan_portfolio (loan_code,member_id,loan_type,principal_amount,interest_rate,loan_term,start_date,end_date,status,ai_credit_score,ai_risk_category,ai_assessment_date) VALUES (?,?,?,?,?,?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL ? MONTH),'Approved',?,?,NOW())");
            $ins->bind_param('sisddiisi',$loan_code,$app['member_id'],$app['loan_type'],$final_amount,$app['interest_rate'],$app['loan_term'],$app['loan_term'],$final_score,$final_risk);
            $ins->execute(); $loan_id = $conn->insert_id; $ins->close();
            $conn->query("UPDATE loan_applications SET loan_id=$loan_id WHERE app_id=$app_id");
        }
        _audit($conn,$user_id,"Loan $decision",'Loan Process',$app_id,"Decision:$decision ₱".number_format($amount,2));
        $msg = match($decision){'Approved'=>"✅ Loan approved! Loan #$loan_id created.",'Rejected'=>"❌ Application rejected.",'Pending'=>"⏳ Set to For Review.",default=>"Done."};
        echo json_encode(['success'=>true,'decision'=>$decision,'loan_id'=>$loan_id,'message'=>$msg]);
        exit();
    }


    // ── POST/GET: Sync from Core 1 API ───────────────────
    if ($action === 'sync_core1') {
        define('CORE1_LOANS_API', 'https://core1.microfinancial-1.com/api/loans');

        // Fetch from Core 1
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => CORE1_LOANS_API,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json','Content-Type: application/json'],
        ]);
        $raw       = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($http_code !== 200 || !$raw) {
            echo json_encode(['success'=>false,'error'=>"Core 1 API error: HTTP $http_code — $curl_err"]);
            exit();
        }

        $data  = json_decode($raw, true);
        if (!$data) { echo json_encode(['success'=>false,'error'=>'Invalid JSON from Core 1']); exit(); }

        // Handle both array and {data:[]} structures
        $loans = isset($data[0]) ? $data : ($data['data'] ?? []);

        if (empty($loans)) {
            echo json_encode(['success'=>true,'synced'=>0,'skipped'=>0,'message'=>'Walang bagong loans mula Core 1.']);
            exit();
        }

        // Only pull Pending loans — filter out already synced
        $synced  = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ($loans as $loan) {
            $loan_code  = trim($loan['loan_code'] ?? '');
            $client_id  = intval($loan['client_id'] ?? 0);
            $amount     = floatval($loan['loan_amount'] ?? 0);
            $loan_type  = trim($loan['loan_type'] ?? 'Regular Loan');
            $term       = intval($loan['loan_term'] ?? 12);
            $rate       = floatval($loan['interest_rate'] ?? 0);
            $purpose    = trim($loan['purpose'] ?? '');
            $core1_status = strtolower($loan['status'] ?? 'pending');

            if (!$loan_code || !$amount) { $errors++; continue; }

            // Only sync pending/new applications
            if (!in_array($core1_status, ['pending','new'])) { $skipped++; continue; }

            // Check if already in loan_applications (by app_code = loan_code)
            $chk = $conn->prepare("SELECT app_id FROM loan_applications WHERE app_code = ? LIMIT 1");
            $chk->bind_param('s', $loan_code);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($exists) { $skipped++; continue; }

            // Find member by client_id (Core 1 client_id = members.member_id or user_id)
            $member_id = 0;
            if ($client_id) {
                $ms = $conn->prepare("SELECT member_id FROM members WHERE member_id=? OR user_id=? LIMIT 1");
                $ms->bind_param('ii', $client_id, $client_id);
                $ms->execute();
                $mrow = $ms->get_result()->fetch_assoc();
                $ms->close();
                $member_id = $mrow ? (int)$mrow['member_id'] : 0;
            }

            // Insert into loan_applications
            $ins = $conn->prepare("INSERT INTO loan_applications
                (app_code,member_id,loan_type,principal_amount,interest_rate,loan_term,
                 purpose,status,created_by)
                VALUES (?,?,?,?,?,?,?,'Pending',?)");
            $ins->bind_param('sisddiisi',
                $loan_code, $member_id, $loan_type,
                $amount, $rate, $term, $purpose, $user_id);
            if ($ins->execute()) {
                $new_id = $conn->insert_id;
                $synced++;
                _audit($conn,$user_id,'Core1 Loan Synced','Loan Process',$new_id,
                    "Synced $loan_code from Core 1 — ₱".number_format($amount,2));
            } else {
                $errors++;
            }
            $ins->close();
        }

        $msg = "✅ Synced $synced new loan applications from Core 1.";
        if ($skipped > 0) $msg .= " ($skipped skipped — already synced or non-pending)";
        if ($errors > 0)  $msg .= " ⚠️ $errors errors.";

        echo json_encode([
            'success' => true,
            'synced'  => $synced,
            'skipped' => $skipped,
            'errors'  => $errors,
            'message' => $msg
        ]);
        exit();
    }

    echo json_encode(['success'=>false,'error'=>"Unknown action: $action"]);

} catch (Throwable $e) {
    error_log('loan_process_action: ' . $e->getMessage());
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

function _risk(int $s): string {
    if ($s>=85) return 'Excellent'; if ($s>=75) return 'Very Good';
    if ($s>=65) return 'Good'; if ($s>=55) return 'Fair'; return 'Poor';
}
function _suggested(int $score, float $req): float {
    $m = match(true){$score>=85=>1.0,$score>=75=>0.9,$score>=65=>0.75,$score>=55=>0.6,default=>0.4};
    return round($req*$m,2);
}
function _audit($conn,$uid,$action,$module,$rid,$remarks) {
    try {
        $ip = $_SERVER['REMOTE_ADDR']??'Unknown';
        $s  = $conn->prepare("INSERT INTO audit_trail (user_id,action_type,module_name,record_id,ip_address,remarks,compliance_status) VALUES (?,?,?,?,?,?,'Compliant')");
        $s->bind_param('ississs',$uid,$action,$module,$rid,$ip,$remarks); $s->execute(); $s->close();
    } catch(Throwable $e){}
}