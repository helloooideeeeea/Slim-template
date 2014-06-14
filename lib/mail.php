<?php
/**
 * Created by PhpStorm.
 * User: sakitayasushi
 * Date: 14/06/15
 * Time: 3:01
 */

function send_token_mail($mailAddress, $tokenName) {
  /*URLの文字列を固定はなんとかならないか*/
  $url = "http://localhost:8888/sign-up/$tokenName";
  $message = '';
  $subject = '';
  $header = '';
  mb_send_mail($mailAddress, $subject, $message, $header);
}