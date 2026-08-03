<?php
include('admin/config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
$msgflag=0;
if(isset($_GET['valcode'])){
    $customers_id = $_GET['customers_id'];
    $customers_password = $_GET['valcode'];
    $usersql = $conn->query("select * from customers where customers_id='$customers_id' limit 1");
    $usercount =$usersql->num_rows;
    if($usercount>0){
        $row = $usersql->fetch_assoc();
        if($row['customers_verify']==1){

            $msgflag=1;
        }else{



            $email = new PHPMailer();
            $email->IsHTML(true);
            //$email->SMTPDebug = 3;

            //Set PHPMailer to use SMTP.
            $email->isSMTP();
            //Set SMTP host name
            $email->Host = defined('PB_SMTP_HOST') ? PB_SMTP_HOST : (getenv('PB_SMTP_HOST') ?: 'mail.supremecluster.com');
            //Set this to true if SMTP host requires authentication to send email
            $email->SMTPAuth = true;
            //Provide username and password
            $email->Username = defined('PB_SMTP_USER') ? PB_SMTP_USER : (getenv('PB_SMTP_USER') ?: 'info@foodhauz.bohollander.com');
            $email->Password = defined('PB_SMTP_PASS') ? PB_SMTP_PASS : (getenv('PB_SMTP_PASS') ?: '');
            //If SMTP requires TLS encryption then set it
            $email->SMTPSecure = "ssl";
            //Set TCP port to connect to
            $email->Port = 465;

            if ($email->Password === '') {
                $msgflag = 0;
            } else {

            $email->setFrom($email->Username,"Pickleball Portal");
            $email->addReplyTo($email->Username, 'Pickleball Portal');
            //$email->SetFrom('webmaster@bohollander.com','Webmaster'); //Name is optional
            $email->Subject   = 'Email Account Verification';

            $email->Body      = "<html>
            <head>
            <title>Eulies Food Hauz - Email Account Verification</title>
            </head>
            <body>
            <p>Welcome to Eulies Food Hauz!</p>
            <p>In order to activate your account kindly click the link below for validation.</p>
            <a href=\"http://www.foodhauz.bohollander.com/email_verify.php?confirm_email=1&customers_id=$customers_id&code=$customers_password\">Click Here</a><br/>
            http://www.foodhauz.bohollander.com/email_verify.php?confirm_email=1&customers_id=$customers_id&code=$customers_password
            <br/>
            <br/>
            Thank you,
            <br/>
            <br/>
            Store Manager
            </body>
            </html>";

            $email->addAddress($row['customers_email']);



            if(!$email->send())
            {
                echo "Mailer Error: " . $email->ErrorInfo;
            }
            else
            {
                echo "Message has been sent successfully";
            }





            $msgflag=2;
            }
        }
        //exit;
    }
    $conn->close();
    header('Location:email_verify.php?msgflag='.$msgflag);
    exit;
}

if(isset($_GET['confirm_email'])){
    $customers_id = $_GET['customers_id'];
    $customers_password = $_GET['code'];
    $stmtheader = $conn->prepare("update customers set customers_verify=1, customers_password='$customers_password' where customers_id=$customers_id");
    $stmtheader->execute();
    header('Location:email_verify.php?msgflag=3');
    exit;
}

include('header.php');

?>
<style>
* {
  box-sizing: border-box;
}

/* Add padding to containers */
.container {
  padding: 16px;
  background-color: white;
}

/* Full-width input fields */
input[type=text], input[type=password] {
  width: 100%;
  padding: 15px;
  margin: 5px 0 22px 0;
  display: inline-block;
  border: none;
  background: #f1f1f1;
}

input[type=text]:focus, input[type=password]:focus {
  background-color: #ddd;
  outline: none;
}

/* Overwrite default styles of hr */
hr {
  border: 1px solid #f1f1f1;
  margin-bottom: 25px;
}

/* Set a style for the submit button */
.registerbtn {
  background-color: #4CAF50;
  color: white;
  padding: 16px 20px;
  margin: 8px 0;
  border: none;
  cursor: pointer;
  width: 100%;
  opacity: 0.9;
}

.registerbtn:hover {
  opacity: 1;
}

/* Add a blue text color to links */
a {
  color: dodgerblue;
}

/* Set a grey background color and center the text of the "sign in" section */
.signin {
  background-color: #f1f1f1;
  text-align: center;
}
.msgbox{
    font-size: 18px;
    margin-top:30px;
    padding: 10px;
    background-color: #95C6CC;
    border-radius: 5px;
    text-align:center;
}

</style>


<div class="main">
    <div class="containerr">
    <?php
    if(isset($_GET['msgflag']) && $_GET['msgflag']==1){
      echo "<p class=\"msgbox\">Email Account already verified..</p>";
    }elseif(isset($_GET['msgflag']) && $_GET['msgflag']==2){
      echo "<p class=\"msgbox\">Thank you for registering... we sent an Email for verification.</p>";
    }elseif(isset($_GET['msgflag']) && $_GET['msgflag']==3){
      echo "<p class=\"msgbox\">Account Successfully verified...Go to this link to login <a href=\"login.php\">click here</a></p>";
    }else{
        echo "<p class=\"msgbox\">Error... there is something wrong.</p>";
    }
    ?>
    </div>
</div>




<!-- End of HubSpot Embed Code -->
<?php
include('footer.php');
?>
