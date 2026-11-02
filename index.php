<?php
session_start();

/*
 Student Doubt Portal - PHP + MySQL (XAMPP)
 Default XAMPP MySQL setup: user=root, blank password.
 The database and tables are created automatically on first visit.
*/
$dbHost = "127.0.0.1";
$dbUser = "root";
$dbPass = "";
$dbName = "student_doubt_portal";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($dbHost, $dbUser, $dbPass);
if ($conn->connect_error) {
    http_response_code(500);
    $detail = htmlspecialchars($conn->connect_error, ENT_QUOTES, "UTF-8");
    die("<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Database connection issue</title>
    <style>body{font-family:Segoe UI,Arial,sans-serif;background:#f6f7fb;color:#1e293b;padding:24px}.card{max-width:760px;margin:5vh auto;background:white;border:1px solid #e8eaf2;border-radius:18px;padding:28px;box-shadow:0 12px 40px #1d23550a}h1{margin-top:0;color:#b4233b}.step{background:#f8fafc;padding:14px;border-radius:10px;margin:12px 0}code{background:#f1f5f9;padding:2px 5px;border-radius:4px}a{color:#6757d9}</style></head><body><div class='card'>
    <h1>Database connection failed</h1><p>Apache is serving your portal, but PHP could not connect to MySQL.</p>
    <div class='step'><b>Connection details:</b> <code>" . $detail . "</code></div>
    <div class='step'><b>1.</b> Open XAMPP Control Panel and click <b>Start</b> beside MySQL. It should turn green.</div>
    <div class='step'><b>2.</b> Open <a href='http://localhost/phpmyadmin'>http://localhost/phpmyadmin</a>. If it does not load, MySQL is not running or has a port issue.</div>
    <div class='step'><b>3.</b> This project defaults to MySQL host <code>127.0.0.1</code>, user <code>root</code>, blank password, and port <code>3306</code>. If you set a MySQL password or changed its port, update the connection settings in <code>index.php</code>.</div>
    <p>After starting MySQL, refresh <a href='index.php'>the portal</a>.</p></div></body></html>");
}
$conn->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
if (!$conn->select_db($dbName)) {
    http_response_code(500);
    die("Could not select/create the project database. Check that the MySQL root account has permission to create databases.");
}
$conn->set_charset("utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','teacher') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS doubts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  answer TEXT NULL,
  status ENUM('Pending','Answered') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  answered_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Create a demo teacher account on first run.
$teacherEmail = "teacher@portal.local";
$check = $conn->prepare("SELECT id FROM users WHERE email=?");
$check->bind_param("s", $teacherEmail);
$check->execute();
$found = $check->get_result()->fetch_assoc();
$check->close();
if (!$found) {
    $teacherName = "Demo Teacher";
    $teacherPassword = password_hash("Teacher@123", PASSWORD_DEFAULT);
    $role = "teacher";
    $stmt = $conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $teacherName, $teacherEmail, $teacherPassword, $role);
    $stmt->execute();
    $stmt->close();
}

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirectHome() { header("Location: index.php"); exit; }

$error = "";
$success = "";
$view = $_GET["view"] ?? "home";

if (isset($_GET["logout"])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "register") {
        $name = trim($_POST["name"] ?? "");
        $email = strtolower(trim($_POST["email"] ?? ""));
        $password = $_POST["password"] ?? "";
        if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            $error = "Enter your name, a valid email, and a password with at least 6 characters.";
            $view = "register";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = "student";
            $stmt = $conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $name, $email, $hash, $role);
            if ($stmt->execute()) {
                $_SESSION["user"] = ["id" => $stmt->insert_id, "name" => $name, "email" => $email, "role" => "student"];
                $stmt->close();
                redirectHome();
            } else {
                $error = "That email is already registered. Please log in instead.";
                $view = "register";
                $stmt->close();
            }
        }
    } elseif ($action === "login") {
        $email = strtolower(trim($_POST["email"] ?? ""));
        $password = $_POST["password"] ?? "";
        $stmt = $conn->prepare("SELECT id,name,email,password,role FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);
            unset($user["password"]);
            $_SESSION["user"] = $user;
            redirectHome();
        } else {
            $error = "Incorrect email or password.";
            $view = "login";
        }
    } elseif ($action === "ask_doubt") {
        if (empty($_SESSION["user"]) || $_SESSION["user"]["role"] !== "student") {
            $error = "Please log in as a student to submit a doubt.";
        } else {
            $subject = trim($_POST["subject"] ?? "");
            $title = trim($_POST["title"] ?? "");
            $description = trim($_POST["description"] ?? "");
            if ($subject === "" || $title === "" || $description === "") {
                $error = "Please complete all doubt fields.";
            } else {
                $uid = (int)$_SESSION["user"]["id"];
                $stmt = $conn->prepare("INSERT INTO doubts (user_id,subject,title,description) VALUES (?,?,?,?)");
                $stmt->bind_param("isss", $uid, $subject, $title, $description);
                if ($stmt->execute()) $success = "Your doubt has been submitted successfully.";
                else $error = "Could not submit your doubt. Please try again.";
                $stmt->close();
                $view = "dashboard";
            }
        }
    } elseif ($action === "answer_doubt") {
        if (empty($_SESSION["user"]) || $_SESSION["user"]["role"] !== "teacher") {
            $error = "Only teachers can answer doubts.";
        } else {
            $doubtId = (int)($_POST["doubt_id"] ?? 0);
            $answer = trim($_POST["answer"] ?? "");
            if ($doubtId > 0 && $answer !== "") {
                $stmt = $conn->prepare("UPDATE doubts SET answer=?, status='Answered', answered_at=NOW() WHERE id=?");
                $stmt->bind_param("si", $answer, $doubtId);
                $stmt->execute();
                $stmt->close();
                $success = "Answer saved successfully.";
            } else $error = "Please enter an answer.";
            $view = "dashboard";
        }
    }
}

$user = $_SESSION["user"] ?? null;
if ($user && $view === "home") $view = "dashboard";

$subjects = ["Computer Science", "Mathematics", "Physics", "Chemistry", "Biology", "English", "Other"];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Doubt Portal</title>
<style>
:root{--ink:#1e293b;--muted:#64748b;--purple:#6757d9;--purple2:#8b5cf6;--bg:#f6f7fb;--line:#e8eaf2;--white:#fff;--green:#16845b;--red:#b4233b}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--ink);line-height:1.55}
a{color:inherit;text-decoration:none}.nav{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:18px max(5%,calc((100% - 1120px)/2));background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:2}
.brand{font-weight:800;font-size:20px;letter-spacing:-.5px;display:flex;align-items:center;gap:10px}.brand-icon{display:grid;place-items:center;background:linear-gradient(135deg,var(--purple),var(--purple2));color:#fff;width:38px;height:38px;border-radius:12px}
.navlinks{display:flex;align-items:center;gap:18px;font-size:14px;color:var(--muted)}.navlinks a:hover{color:var(--purple)}
.wrap{width:min(1120px,90%);margin:34px auto 70px}.hero{background:linear-gradient(120deg,#27234f,#5146a8 62%,#8a68e8);border-radius:24px;padding:46px;color:#fff;display:grid;grid-template-columns:1.4fr .8fr;gap:28px;align-items:center;overflow:hidden}
.eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:2px;font-weight:800;color:#ded9ff}.hero h1{font-size:clamp(30px,4vw,48px);line-height:1.12;letter-spacing:-1.5px;margin:12px 0}.hero p{color:#e5e2ff;max-width:560px}.hero-art{font-size:95px;text-align:center;filter:drop-shadow(0 14px 20px #21194c88)}
.btn{border:0;display:inline-flex;align-items:center;justify-content:center;padding:11px 17px;border-radius:11px;font-weight:700;cursor:pointer;font-size:14px;transition:.15s}.btn-primary{background:var(--purple);color:#fff}.btn-primary:hover{background:#5545c4;transform:translateY(-1px)}.btn-white{background:#fff;color:#4c3eb5;margin-top:10px}.btn-outline{border:1px solid var(--line);background:#fff;color:var(--ink)}.btn-danger{background:#fff0f2;color:var(--red)}
.section-head{display:flex;justify-content:space-between;align-items:end;gap:15px;margin:32px 0 16px}.section-head h2{margin:0;font-size:24px;letter-spacing:-.5px}.muted{color:var(--muted);font-size:14px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:22px;box-shadow:0 6px 24px #25315a06}.feature-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:13px;background:#f0edff;font-size:22px}.card h3{margin:13px 0 6px;font-size:17px}.card p{margin:0;color:var(--muted);font-size:14px}
.form-card{max-width:560px;margin:30px auto;background:#fff;border:1px solid var(--line);border-radius:20px;padding:30px;box-shadow:0 12px 40px #1d23550a}.form-card h1{margin:0 0 4px;letter-spacing:-.7px}.field{margin-top:17px}.field label{display:block;font-size:13px;font-weight:700;margin-bottom:7px}.field input,.field textarea,.field select{width:100%;padding:12px 13px;border:1px solid #dfe2ed;border-radius:10px;font:inherit;font-size:14px;background:#fff;outline:none}.field input:focus,.field textarea:focus,.field select:focus{border-color:var(--purple);box-shadow:0 0 0 3px #6757d91c}.field textarea{min-height:125px;resize:vertical}.full{width:100%;margin-top:20px}.notice{padding:12px 15px;border-radius:12px;margin:16px 0;font-size:14px}.notice.error{background:#fff0f2;color:#a21d37}.notice.success{background:#eafaf2;color:#087548}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin:20px 0}.stat{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px}.stat span{font-size:13px;color:var(--muted)}.stat strong{display:block;font-size:28px;margin-top:4px}
.doubt{background:#fff;border:1px solid var(--line);border-radius:17px;padding:22px;margin:14px 0}.doubt-top{display:flex;justify-content:space-between;gap:12px;align-items:start}.doubt h3{margin:9px 0 5px}.pill{display:inline-block;border-radius:999px;padding:4px 10px;font-size:11px;font-weight:800;background:#fff5dc;color:#946200}.pill.answered{background:#e5f8ee;color:#087548}.subject{font-size:12px;font-weight:800;color:var(--purple);text-transform:uppercase;letter-spacing:.7px}.answer{margin-top:17px;background:#f5f3ff;border-radius:12px;padding:15px}.answer b{display:block;font-size:12px;color:#5845c6;margin-bottom:5px}.teacher-form{margin-top:16px;border-top:1px solid var(--line);padding-top:14px}.teacher-form textarea{min-height:80px}
.empty{text-align:center;padding:36px 18px;color:var(--muted)}footer{text-align:center;padding:24px;color:#8490a5;font-size:12px}
@media(max-width:760px){.hero{grid-template-columns:1fr;padding:30px}.hero-art{display:none}.grid{grid-template-columns:1fr}.nav{padding:14px 5%}.navlinks{gap:10px;font-size:12px}.stats{grid-template-columns:1fr}.doubt-top{flex-direction:column}.wrap{margin-top:22px}}
</style>
</head>
<body>
<header class="nav">
  <a class="brand" href="index.php"><span class="brand-icon">✦</span> DoubtDesk</a>
  <nav class="navlinks">
    <?php if ($user): ?>
      <span>Hi, <?=e($user["name"])?></span>
      <a href="index.php?view=dashboard">Dashboard</a>
      <a href="index.php?logout=1">Logout ↗</a>
    <?php else: ?>
      <a href="index.php?view=login">Log in</a>
      <a class="btn btn-primary" href="index.php?view=register">Get started</a>
    <?php endif; ?>
  </nav>
</header>
<main class="wrap">
<?php if ($error): ?><div class="notice error"><?=e($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="notice success"><?=e($success)?></div><?php endif; ?>

<?php if (!$user && $view === "home"): ?>
  <section class="hero">
    <div><div class="eyebrow">Learn together. Grow together.</div><h1>Every question deserves an answer.</h1><p>A simple space to ask academic questions, get guidance from teachers, and keep learning without hesitation.</p><a class="btn btn-white" href="index.php?view=register">Create free account&nbsp; →</a></div>
    <div class="hero-art">💬📚</div>
  </section>
  <div class="section-head"><div><h2>Everything you need to learn</h2><div class="muted">A helpful place for students and teachers.</div></div></div>
  <div class="grid">
    <article class="card"><div class="feature-icon">🙋</div><h3>Ask without hesitation</h3><p>Post your doubt with a subject, title and detailed description.</p></article>
    <article class="card"><div class="feature-icon">🧑‍🏫</div><h3>Teacher guidance</h3><p>Teachers can review student questions and share helpful answers.</p></article>
    <article class="card"><div class="feature-icon">✓</div><h3>Track progress</h3><p>See which doubts are pending and which have been answered.</p></article>
  </div>
  <div class="section-head"><div><h2>Ready to clear your doubts?</h2><div class="muted">Create a student account to get started.</div></div><a class="btn btn-primary" href="index.php?view=register">Join the portal →</a></div>

<?php elseif (!$user && $view === "register"): ?>
  <section class="form-card"><h1>Create your account</h1><p class="muted">Join DoubtDesk as a student.</p>
    <form method="post"><input type="hidden" name="action" value="register">
      <div class="field"><label>Full name</label><input name="name" required maxlength="100" placeholder="Your name"></div>
      <div class="field"><label>Email address</label><input type="email" name="email" required placeholder="you@example.com"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required minlength="6" placeholder="At least 6 characters"></div>
      <button class="btn btn-primary full" type="submit">Create account →</button>
    </form><p class="muted" style="text-align:center;margin-top:18px">Already registered? <a style="color:#6757d9;font-weight:700" href="index.php?view=login">Log in</a></p>
  </section>

<?php elseif (!$user && $view === "login"): ?>
  <section class="form-card"><h1>Welcome back</h1><p class="muted">Log in to your DoubtDesk account.</p>
    <form method="post"><input type="hidden" name="action" value="login">
      <div class="field"><label>Email address</label><input type="email" name="email" required placeholder="you@example.com"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required placeholder="Your password"></div>
      <button class="btn btn-primary full" type="submit">Log in →</button>
    </form>
    <div style="margin-top:22px;padding:14px;background:#f5f3ff;border-radius:12px;font-size:13px"><b>Demo teacher account</b><br>Email: <code>teacher@portal.local</code><br>Password: <code>Teacher@123</code></div>
    <p class="muted" style="text-align:center;margin-top:18px">New student? <a style="color:#6757d9;font-weight:700" href="index.php?view=register">Create an account</a></p>
  </section>

<?php elseif ($user): ?>
  <?php
    $isTeacher = $user["role"] === "teacher";
    if ($isTeacher) {
      $statsResult = $conn->query("SELECT COUNT(*) total, SUM(status='Pending') pending, SUM(status='Answered') answered FROM doubts");
      $stats = $statsResult->fetch_assoc();
      $stmt = $conn->prepare("SELECT d.*,u.name student_name,u.email student_email FROM doubts d JOIN users u ON d.user_id=u.id ORDER BY (d.status='Pending') DESC,d.created_at DESC");
    } else {
      $uid = (int)$user["id"];
      $stmtStats = $conn->prepare("SELECT COUNT(*) total, SUM(status='Pending') pending, SUM(status='Answered') answered FROM doubts WHERE user_id=?");
      $stmtStats->bind_param("i",$uid);$stmtStats->execute();$stats=$stmtStats->get_result()->fetch_assoc();$stmtStats->close();
      $stmt = $conn->prepare("SELECT d.*,u.name student_name,u.email student_email FROM doubts d JOIN users u ON d.user_id=u.id WHERE d.user_id=? ORDER BY d.created_at DESC");
      $stmt->bind_param("i",$uid);
    }
    $stmt->execute(); $doubts = $stmt->get_result();
  ?>
  <div class="section-head"><div><div class="eyebrow" style="color:#6757d9"><?= $isTeacher ? "TEACHER WORKSPACE" : "STUDENT WORKSPACE" ?></div><h2><?= $isTeacher ? "Doubt management" : "Your learning dashboard" ?></h2><div class="muted"><?= $isTeacher ? "Review questions and share answers with students." : "Ask questions, follow replies and keep moving forward." ?></div></div>
  <?php if (!$isTeacher): ?><a class="btn btn-primary" href="#ask">＋ Ask a doubt</a><?php endif; ?></div>
  <div class="stats">
    <div class="stat"><span>Total doubts</span><strong><?= (int)($stats["total"] ?? 0) ?></strong></div>
    <div class="stat"><span>Pending</span><strong><?= (int)($stats["pending"] ?? 0) ?></strong></div>
    <div class="stat"><span>Answered</span><strong><?= (int)($stats["answered"] ?? 0) ?></strong></div>
  </div>
  <?php if (!$isTeacher): ?>
  <section class="form-card" id="ask" style="max-width:none;margin:24px 0;padding:24px"><h2 style="margin:0">Ask a new doubt</h2><p class="muted">Give your teacher enough detail to understand the question.</p>
    <form method="post"><input type="hidden" name="action" value="ask_doubt">
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
        <div class="field" style="margin-top:0"><label>Subject</label><select name="subject" required><option value="">Select subject</option><?php foreach($subjects as $s): ?><option><?=e($s)?></option><?php endforeach; ?></select></div>
        <div class="field" style="margin-top:0"><label>Doubt title</label><input name="title" maxlength="180" required placeholder="What are you stuck on?"></div>
      </div>
      <div class="field"><label>Description</label><textarea name="description" required placeholder="Explain your question here..."></textarea></div>
      <button class="btn btn-primary" type="submit">Submit doubt →</button>
    </form>
  </section>
  <?php endif; ?>
  <div class="section-head"><div><h2><?= $isTeacher ? "All student doubts" : "Your doubts" ?></h2><div class="muted"><?= $isTeacher ? "Pending questions are shown first." : "Your submitted questions and teacher replies." ?></div></div></div>
  <?php if ($doubts->num_rows === 0): ?><div class="card empty"><div style="font-size:38px">💭</div><h3>No doubts yet</h3><p><?= $isTeacher ? "Student questions will appear here." : "Submit your first doubt using the form above." ?></p></div><?php endif; ?>
  <?php while($d = $doubts->fetch_assoc()): ?>
    <article class="doubt">
      <div class="doubt-top"><div><span class="subject"><?=e($d["subject"])?></span><h3><?=e($d["title"])?></h3></div><span class="pill <?= $d["status"] === "Answered" ? "answered" : "" ?>"><?=e($d["status"])?></span></div>
      <?php if($isTeacher): ?><div class="muted">Asked by <?=e($d["student_name"])?> · <?=e($d["student_email"])?></div><?php endif; ?>
      <p style="white-space:pre-wrap"><?=e($d["description"])?></p>
      <?php if(!empty($d["answer"])): ?><div class="answer"><b>TEACHER'S ANSWER</b><div style="white-space:pre-wrap"><?=e($d["answer"])?></div></div><?php endif; ?>
      <?php if($isTeacher): ?><form method="post" class="teacher-form"><input type="hidden" name="action" value="answer_doubt"><input type="hidden" name="doubt_id" value="<?=(int)$d["id"]?>"><div class="field" style="margin-top:0"><label><?= $d["status"] === "Answered" ? "Update answer" : "Write an answer" ?></label><textarea name="answer" required placeholder="Type a helpful response..."><?=e($d["answer"] ?? "")?></textarea></div><button class="btn btn-primary" type="submit"><?= $d["status"] === "Answered" ? "Update answer" : "Post answer" ?> →</button></form><?php endif; ?>
      <div class="muted" style="margin-top:13px;font-size:12px">Submitted <?=e(date("d M Y, g:i A", strtotime($d["created_at"])))?></div>
    </article>
  <?php endwhile; $stmt->close(); ?>
<?php else: ?>
  <section class="form-card"><h1>Page not found</h1><a class="btn btn-primary" href="index.php">Return home</a></section>
<?php endif; ?>
</main>
<footer>© <?=date("Y")?> DoubtDesk · Student Doubt Portal · Built with PHP & MySQL</footer>
</body>
</html>
