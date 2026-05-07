<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    public $Host = '';
    public $Port = 587;
    public $SMTPAuth = true;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = 'tls';
    public $CharSet = 'UTF-8';
    public $Subject = '';
    public $Body = '';
    public $ErrorInfo = '';

    public function __construct($exceptions = false) {}
    public function isSMTP() {}
    public function setFrom($address, $name = '') {}
    public function addAddress($address, $name = '') {}
    public function isHTML($isHtml = true) {}
    public function send() { return true; }
    public function smtpConnect() { return true; }
    public function smtpClose() {}
}
