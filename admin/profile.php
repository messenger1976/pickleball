<?php
include('config.php');
fh_auth_require_login();

$userId = (int)$_SESSION['user_id'];
$userSql = $conn->query("SELECT u.*, ba.business_name
    FROM user u
    LEFT JOIN business_account ba ON u.user_business_account = ba.business_id
    WHERE u.user_id = $userId
    LIMIT 1");
$user = ($userSql && ($row = $userSql->fetch_assoc())) ? $row : null;

if (!$user) {
    header('Location: logout.php');
    exit;
}

fh_auth_set_session($user);

$avatarUrl = fh_auth_avatar_url($user['user_avatar']);
$hasCustomAvatar = !empty($user['user_avatar']);
$fullname = trim($user['user_fullname']);
$initials = '';
$parts = preg_split('/\s+/', $fullname);
if (!empty($parts[0])) {
    $initials .= strtoupper(substr($parts[0], 0, 1));
}
if (!empty($parts[1])) {
    $initials .= strtoupper(substr($parts[1], 0, 1));
}
if ($initials === '') {
    $initials = strtoupper(substr($user['user_name'], 0, 1));
}

$restaurantLabel = !empty($user['business_name'])
    ? $user['business_name']
    : (fh_auth_is_super_admin() ? 'Portal / Super Admin' : '—');

$titleheader = 'My Profile';
include('header.php');
include('navigation.php');
?>

<link rel="stylesheet" href="../css/portal-order.css?v=20260714b" type="text/css" />
<style>
.admin-profile-layout {
    display: grid;
    grid-template-columns: minmax(220px, 280px) 1fr;
    gap: 20px;
    align-items: start;
}
.admin-profile-avatar-card,
.admin-profile-form-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}
.admin-profile-avatar-card {
    padding: 24px 20px;
    text-align: center;
}
.admin-profile-avatar-wrap {
    width: 120px;
    height: 120px;
    margin: 0 auto 14px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #e2e8f0;
    background: #f1f5f9;
    position: relative;
}
.admin-profile-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.admin-profile-avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 800;
    color: #2d6a4f;
    background: linear-gradient(145deg, #d8f3dc, #b7e4c7);
}
.admin-profile-avatar-card h2 {
    margin: 0 0 4px;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
}
.admin-profile-meta {
    margin: 0 0 16px;
    font-size: 13px;
    color: #64748b;
}
.admin-profile-upload-btn {
    display: inline-block;
    padding: 9px 16px;
    border-radius: 8px;
    background: #2d6a4f;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}
.admin-profile-upload-btn:hover {
    background: #1b4332;
}
.admin-profile-upload-hint {
    margin: 10px 0 0;
    font-size: 12px;
    color: #94a3b8;
}
.admin-profile-form-card .portal-cart-panel-head {
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}
.admin-profile-form-card .portal-cart-panel-head h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
}
.admin-profile-form-body {
    padding: 20px 22px 24px;
}
.admin-profile-form-body label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.admin-profile-form-body input[type="text"],
.admin-profile-form-body input[type="email"],
.admin-profile-form-body input[type="password"] {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
    background: #fff;
}
.admin-profile-form-body input[readonly] {
    background: #f8fafc;
    color: #64748b;
}
.admin-profile-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.admin-profile-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
}
.portal-login-panel-alert {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    font-size: 13px;
}
.portal-login-panel-alert.is-success {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #065f46;
}
@media (max-width: 800px) {
    .admin-profile-layout {
        grid-template-columns: 1fr;
    }
    .admin-profile-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>My Profile</h1>
        <p>Update your account details and profile photo.</p>
    </div>

    <div class="admin-profile-layout">
        <aside class="admin-profile-avatar-card">
            <div class="admin-profile-avatar-wrap">
                <?php if ($hasCustomAvatar): ?>
                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Profile photo" class="admin-profile-avatar-img" id="profileAvatarImg">
                    <div class="admin-profile-avatar-placeholder" id="profileAvatarPlaceholder" style="display:none;"><?php echo htmlspecialchars($initials); ?></div>
                <?php else: ?>
                    <div class="admin-profile-avatar-placeholder" id="profileAvatarPlaceholder"><?php echo htmlspecialchars($initials); ?></div>
                    <img src="" alt="Profile photo" class="admin-profile-avatar-img" id="profileAvatarImg" style="display:none;">
                <?php endif; ?>
            </div>
            <h2 id="profileDisplayName"><?php echo htmlspecialchars($fullname !== '' ? $fullname : $user['user_name']); ?></h2>
            <p class="admin-profile-meta"><?php echo htmlspecialchars(fh_auth_role_label($user['user_role'])); ?></p>

            <form id="frmAvatarUpload" enctype="multipart/form-data">
                <label class="admin-profile-upload-btn" for="avatarInput">
                    <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                    Change Photo
                </label>
            </form>
            <p class="admin-profile-upload-hint">JPG, PNG, GIF, or WebP · Max 2 MB</p>
            <div id="avatarMsgbox" class="portal-login-panel-alert" style="display:none" role="alert"></div>
        </aside>

        <div class="admin-profile-form-card">
            <div class="portal-cart-panel-head">
                <h2>Account Information</h2>
            </div>
            <div class="admin-profile-form-body">
                <div id="profileMsgbox" class="portal-login-panel-alert" style="display:none" role="alert"></div>

                <form id="frmMyProfile" action="#" method="post">
                    <label for="user_name">Username *</label>
                    <input type="text" id="user_name" maxlength="15" value="<?php echo htmlspecialchars($user['user_name']); ?>" required>

                    <label for="user_fullname">Full Name *</label>
                    <input type="text" id="user_fullname" value="<?php echo htmlspecialchars($user['user_fullname']); ?>" required>

                    <label for="user_email">Email</label>
                    <input type="email" id="user_email" value="<?php echo htmlspecialchars($user['user_email']); ?>">

                    <label for="user_password">Password <span class="portal-order-modal-hint" style="display:inline;margin:0;">(leave blank to keep current)</span></label>
                    <input type="password" id="user_password" autocomplete="new-password" placeholder="At least 6 characters">

                    <div class="admin-profile-row">
                        <div>
                            <label for="user_role_display">Role</label>
                            <input type="text" id="user_role_display" value="<?php echo htmlspecialchars(fh_auth_role_label($user['user_role'])); ?>" readonly>
                        </div>
                        <div>
                            <label for="user_restaurant_display">Restaurant</label>
                            <input type="text" id="user_restaurant_display" value="<?php echo htmlspecialchars($restaurantLabel); ?>" readonly>
                        </div>
                    </div>

                    <div class="admin-profile-actions">
                        <button type="submit" class="portal-login-btn portal-login-btn-primary" id="btnSaveProfile">Save Changes</button>
                        <a href="index.php" class="portal-login-btn portal-login-btn-secondary" style="text-decoration:none;text-align:center;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function ($) {
    function showAlert(sel, msg, ok) {
        var $el = $(sel);
        $el.toggleClass('is-success', !!ok).text(msg).show();
    }

    function updateSidebarAvatar(url) {
        if (!url) {
            return;
        }
        $('.admin-sidebar-avatar').attr('src', url);
    }

    $('#frmMyProfile').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#btnSaveProfile').prop('disabled', true).text('Saving…');
        $('#profileMsgbox').hide().text('');

        $.post('process.php', {
            function: 'SaveMyProfile',
            user_name: $('#user_name').val(),
            user_fullname: $('#user_fullname').val(),
            user_email: $('#user_email').val(),
            user_password: $('#user_password').val()
        }, function (res) {
            $btn.prop('disabled', false).text('Save Changes');
            if (res.msg === true) {
                var name = $('#user_fullname').val().trim() || $('#user_name').val().trim();
                $('#profileDisplayName').text(name);
                $('.admin-sidebar-profile-name, .admin-user-name').text(name);
                $('#user_password').val('');
                showAlert('#profileMsgbox', 'Profile updated successfully.', true);
                return;
            }
            showAlert('#profileMsgbox', res.error || 'Unable to save profile.', false);
        }, 'json').fail(function () {
            $btn.prop('disabled', false).text('Save Changes');
            showAlert('#profileMsgbox', 'Something went wrong. Please try again.', false);
        });
    });

    $('#avatarInput').on('change', function () {
        var file = this.files && this.files[0];
        if (!file) {
            return;
        }
        var ext = (file.name.split('.').pop() || '').toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) === -1) {
            showAlert('#avatarMsgbox', 'Please choose a JPG, PNG, GIF, or WebP image.', false);
            this.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showAlert('#avatarMsgbox', 'Image must be 2 MB or smaller.', false);
            this.value = '';
            return;
        }

        var formData = new FormData();
        formData.append('function', 'UploadAdminAvatar');
        formData.append('avatar', file);
        showAlert('#avatarMsgbox', 'Uploading photo…', true);

        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.msg === true && res.avatar_url) {
                    $('#profileAvatarImg').attr('src', res.avatar_url).show();
                    $('#profileAvatarPlaceholder').hide();
                    updateSidebarAvatar(res.avatar_url);
                    showAlert('#avatarMsgbox', 'Photo updated.', true);
                    return;
                }
                showAlert('#avatarMsgbox', res.error || 'Could not upload photo.', false);
            },
            error: function () {
                showAlert('#avatarMsgbox', 'Upload failed. Please try again.', false);
            },
            complete: function () {
                $('#avatarInput').val('');
            }
        });
    });
})(jQuery);
</script>

<?php include('footer.php'); ?>
