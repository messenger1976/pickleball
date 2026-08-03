<?php

if(isset($_POST['submit1'])){
//extract data from the post
//set POST variables
$url = 'http://www.txtlibre.com/send_message-202001031230.php';
$fields = array(
    'frm_senders_namex' => urlencode($_POST['frm_senders_namex']),
    'frm_sen' => urlencode($_POST['frm_sen']),
    'frm_senders_num' => urlencode($_POST['frm_senders_num']),
    'frm_recepient_num' => urlencode($_POST['frm_recepient_num']),
    'frm_messagex' => urlencode($_POST['frm_messagex'])
);

//url-ify the data for the POST
foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
rtrim($fields_string, '&');

//open connection
$ch = curl_init();

//set the url, number of POST vars, POST data
curl_setopt($ch,CURLOPT_URL, $url);
curl_setopt($ch,CURLOPT_POST, count($fields));
curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_HEADER, false);
//execute post
$result = curl_exec($ch);

//close connection
curl_close($ch);
echo $result;
 //header('Location: sendsms.php');
 exit;
}

?>
<html>
    <head>
        <script type="text/javascript" src="admin/js/jquery.min.js"></script>
    <style>
    #my-iframe{
        display: block;
    }

    </style>
    </head>
    <body>

<iframe name="my-iframe" id="my-iframe"></iframe>
<!--http://www.txtlibre.com/send_message-202001031230.php-->
<form id="formID"  target="my-iframe" autocomplete="off" name="myform" method="POST" action="http://www.txtlibre.com/send_message-202001031230.php">

                                            <div class="bigspace"><input type="Text" name="frm_senders_namex"    autocomplete="off" maxlength="15" class="rounded" data-validation-engine="validate[required]"/ placeholder="Your Name" ><input type="hidden" name="frm_sen" value="0530"/> </div>
                                            <div class="bigspace"><input type="Text" name="frm_senders_num"   autocomplete="off" class="rounded" maxlength="13"    data-validation-engine="validate[required]"  placeholder="Your Mobile # (0918xxxxxxx)" /> </div>
                                            <div class="bigspace"> <input type="Text" name="frm_recepient_num"   autocomplete="off" class="rounded" maxlength="13"     data-validation-engine="validate[required]" placeholder="Your Friends Mobile # (0918xxxxxxx)" /> </div>

                                            <div class="bigspace">
                                                    <textarea  name="frm_messagex" autocomplete="off" maxlength="180" placeholder="Your Message" ></textarea>
                                                    <div align=center><input type="submit" id="btnSubmit" name="submit" value="send message"/></div>
                                            </div>
</form>

<script>
$(document).ready(function() {
    $('#formID').submit(function(event){

    })
    $('#btnSubmit').click(function(){
        $("#formID").submit();
        $('iframe').load(function() {
            window.location.href = 'sendsms.php';
        });
//       event.preventDefault();
    })

});
</script>
    </body>
</html>
