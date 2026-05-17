<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID']) || !isset($_SESSION['userType']) || $_SESSION['userType'] != 'admin') {
    header("Location: login.php?msg=You must log in as admin first");
    exit();
}

$userID = $_SESSION['userID'];

$sqlAdmin = "SELECT * FROM user WHERE id = ?";
$stmtAdmin = mysqli_prepare($conn, $sqlAdmin);
mysqli_stmt_bind_param($stmtAdmin, "i", $userID);
mysqli_stmt_execute($stmtAdmin);
$resultAdmin = mysqli_stmt_get_result($stmtAdmin);

if (mysqli_num_rows($resultAdmin) == 0) {
    header("Location: login.php?msg=Admin account not found");
    exit();
}

$admin = mysqli_fetch_assoc($resultAdmin);

$sqlReports = "SELECT report.id AS reportID, report.recipeID, recipe.name, user.id AS creatorID, user.firstName, user.lastName, user.photoFileName
               FROM report
               JOIN recipe ON report.recipeID = recipe.id
               JOIN user ON recipe.userID = user.id
               ORDER BY report.id DESC";
$reports = mysqli_query($conn, $sqlReports);

$sqlBlocked = "SELECT * FROM blockeduser ORDER BY id DESC";
$blockedUsers = mysqli_query($conn, $sqlBlocked);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Page - Little Chefs</title>
<link href="https://fonts.googleapis.com/css2?family=Comic+Neue:wght@400;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--primary:#FF9AA2;--accent:#FFDAC1;--green:#B5EAD7;--blue:#C7CEEA;--yellow:#FFE5B4;--dark:#5D576B;--light:#FFF9F5;--radius:16px;--shadow:0 8px 25px rgba(93,87,107,0.10);--t:all 0.25s ease;}
*{margin:0;padding:0;box-sizing:border-box;} html,body{height:100%;}
body{font-family:'Nunito',sans-serif;background:linear-gradient(135deg,var(--accent) 0%,var(--blue) 100%);min-height:100vh;color:var(--dark);}
.page-pad{padding:20px;}
.site-header{background:linear-gradient(to right,var(--primary),var(--blue));padding:15px 40px;display:flex;justify-content:space-between;align-items:center;}
.header-left{display:flex;align-items:center;gap:15px;} .logo{width:130px;height:auto;filter:drop-shadow(0 4px 6px rgba(0,0,0,0.15));}
.site-header h1{font-family:'Comic Neue',cursive;font-size:2.4rem;color:#fff;} nav a{margin-left:25px;color:#fff;text-decoration:none;font-weight:700;cursor:pointer;padding-bottom:5px;position:relative;} nav a:hover{color:var(--yellow);} nav a.active{color:var(--yellow);border-bottom:3px solid var(--yellow);}
footer{background:var(--green);padding:25px;text-align:center;font-weight:600;}
.container{max-width:1000px;margin:0 auto;background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;border:6px solid var(--yellow);} 
.admin-header{background:#ffffff;padding:22px 26px;border-bottom:3px solid rgba(255,218,193,0.9);} .topbar{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.welcome{font-family:'Comic Neue',cursive;font-size:2.1rem;font-weight:700;} .logout{font-weight:900;color:var(--dark);text-decoration:underline;cursor:pointer;padding:8px 10px;border-radius:10px;transition:var(--t);} .logout:hover{background:rgba(199,206,234,0.35);} 
main{background:var(--light);padding:24px;} .panel{background:#fff;border-radius:14px;padding:20px;border:2px solid rgba(93,87,107,0.25);margin-bottom:18px;} .panel-title{font-family:'Comic Neue',cursive;font-size:1.9rem;margin-bottom:12px;font-weight:700;}
.info-lines{line-height:1.9;font-size:1.05rem;} .info-lines b{display:inline-block;min-width:140px;} .section-title{font-family:'Comic Neue',cursive;font-size:2rem;margin:18px 0 12px;font-weight:700;}
table{width:100%;border-collapse:collapse;background:#fff;border:2px solid rgba(93,87,107,0.35);} th,td{border:2px solid rgba(93,87,107,0.35);padding:14px 12px;vertical-align:top;} th{background:#FFDAC1;color:#5D576B;font-weight:900;text-align:center;} td{background:#fff;}
a{color:inherit;text-decoration:none;} .recipe-link{color:#5D576B;font-weight:600;cursor:pointer;} .recipe-link:hover{color:#FF9AA2;} .creator-cell{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.avatar{width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid var(--accent);background:#fff;} .radio-group{display:flex;flex-direction:column;gap:10px;margin-bottom:12px;} .radio-item{display:flex;align-items:center;gap:10px;font-weight:800;}
.submit-btn{border:none;cursor:pointer;border-radius:10px;padding:12px 18px;font-weight:900;font-family:'Comic Neue',cursive;background:var(--green);color:var(--dark);box-shadow:0 5px 0 #8FD5B7;transition:var(--t);min-width:120px;}
.submit-btn:hover{transform:translateY(-3px);box-shadow:0 8px 0 #8FD5B7;} .submit-btn:disabled{opacity:0.5;cursor:not-allowed;transform:none;}
.small-note{font-size:0.9rem;opacity:0.8;margin-top:10px;line-height:1.6;} .msg-box{background:#fff6d8;border:2px solid #ffe08a;padding:12px;border-radius:12px;margin-bottom:18px;font-weight:700;}
@media (max-width:820px){.welcome{font-size:1.8rem;} th,td{padding:12px 10px;} .creator-cell{flex-direction:column;align-items:flex-start;} .avatar{width:50px;height:50px;}}
</style>
</head>
<body>
<header class="site-header">
<div class="header-left"><img src="photo.png" alt="Kids Recipes Logo" class="logo"><h1>LittleChefs</h1></div>
<nav><a href="index.html">Home</a><a href="user.php">Users</a><a class="active" href="admin.php">Admins</a></nav>
</header>
<div class="page-pad"><div class="container">
<div class="admin-header"><div class="topbar"><div class="welcome">Welcome <?php echo $admin['firstName']; ?></div><a href="logout.php"><div class="logout">Logout</div></a></div></div>
<main>
<?php if (isset($_GET['msg'])) { ?><div class="msg-box"><?php echo $_GET['msg']; ?></div><?php } ?>
<section class="panel"><div class="panel-title">My Information</div><div class="info-lines"><div><b>Name</b><span><?php echo $admin['firstName'] . " " . $admin['lastName']; ?></span></div><div><b>Email address</b><span><?php echo $admin['emailAddress']; ?></span></div></div></section>
<div class="section-title">📝Reported Recipes</div>
<section class="panel" style="padding:0; overflow:auto;">
<table id="reportsTable">
<thead><tr><th style="width:40%;">Recipe Name</th><th style="width:30%;">Recipe Creator</th><th style="width:30%;">Action</th></tr></thead>
<tbody>
<?php if (mysqli_num_rows($reports) > 0) { ?>
<?php while($row = mysqli_fetch_assoc($reports)) { ?>
<?php
$creatorPhoto = (!empty($row['photoFileName']) &&
    file_exists(__DIR__ . "/images/users/" . $row['photoFileName']))
    ? "images/users/" . $row['photoFileName']
    : "images/users/profile.png";
?>
<tr id="report-row-<?php echo $row['reportID']; ?>">
<td><a href="view-recipe.php?id=<?php echo $row['recipeID']; ?>"><div class="recipe-link"><?php echo $row['name']; ?></div></a></td>
<td><div class="creator-cell"><div><div style="font-weight:900;"><?php echo $row['firstName'] . " " . $row['lastName']; ?></div><div class="small-note">Creator</div></div><img class="avatar" src="<?php echo $creatorPhoto; ?>" alt="creator photo"></div></td>
<td>
    <div class="radio-group">
        <label class="radio-item"><input type="radio" name="action-<?php echo $row['reportID']; ?>" value="block" required> Block User</label>
        <label class="radio-item"><input type="radio" name="action-<?php echo $row['reportID']; ?>" value="dismiss"> Dismiss Report</label>
    </div>
    <button class="submit-btn"
            type="button"
            data-report-id="<?php echo $row['reportID']; ?>"
            data-recipe-id="<?php echo $row['recipeID']; ?>"
            data-user-id="<?php echo $row['creatorID']; ?>">
        Submit
    </button>
</td>
</tr>
<?php } ?>
<?php } else { ?><tr id="no-reports-row"><td colspan="3" style="text-align:center;">No reports found.</td></tr><?php } ?>
</tbody>
</table>
</section>
<div class="section-title" style="margin-top:22px;">🔒Blocked Users List</div>
<section class="panel" style="padding:0; overflow:auto;"><table><thead><tr><th>Name</th><th>Email Address</th></tr></thead><tbody>
<?php if (mysqli_num_rows($blockedUsers) > 0) { ?>
<?php while($blocked = mysqli_fetch_assoc($blockedUsers)) { ?>
<tr><td><?php echo $blocked['firstName'] . " " . $blocked['lastName']; ?></td><td><?php echo $blocked['emailAddress']; ?></td></tr>
<?php } ?>
<?php } else { ?><tr><td colspan="2" style="text-align:center;">No blocked users found.</td></tr><?php } ?>
</tbody></table></section>
</main></div></div>
<footer>© 2026 Kids Recipes — Made with 💖 for little ones</footer>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function () {

    // When any Submit button is clicked in the reports table
    $('#reportsTable').on('click', '.submit-btn', function () {
        var btn      = $(this);
        var reportID = btn.data('report-id');
        var recipeID = btn.data('recipe-id');
        var userID   = btn.data('user-id');

        // Get the selected radio value for this specific report row
        var action = $('input[name="action-' + reportID + '"]:checked').val();

        if (!action) {
            alert('Please select an action first.');
            return;
        }

        // Disable button while processing
        btn.prop('disabled', true).text('Processing...');

        var formData = new FormData();
        formData.append('recipeID', recipeID);
        formData.append('userID', userID);
        formData.append('reportID', reportID);
        formData.append('action', action);

        fetch('handle_report.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function(response) {
            return response.text();
        })
        .then(function(text) {
                var result = text.trim().indexOf('true') !== -1;
                if (result) {
                    var tbody = $('#reportsTable tbody');
                    $('#report-row-' + reportID).remove();
                    if (tbody.find('tr').length === 0) {
                        tbody.append('<tr id="no-reports-row"><td colspan="3" style="text-align:center;">No reports found.</td></tr>');
                    }
                } else {
                    alert('Something went wrong. Please try again.');
                    btn.prop('disabled', false).text('Submit');
                }
            })
        .catch(function(err) {
            alert('FETCH ERROR: ' + err);
        });
    });

});
</script>
</body>
</html>
