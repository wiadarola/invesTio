<?php

function emptyInputSignup($uname, $email, $pwd, $pwdRepeat)
{
    $result = false;
    if (
        empty($uname) || empty($email) ||
        empty($pwd) || empty($pwdRepeat)
    ) {
        $result = true;
    }
    return $result;
}

function emptyInputLogin($uname, $pwd)
{
    $result = false;
    if (empty($uname) || empty($pwd)) {
        $result = true;
    }
    return $result;
}

function invalidUsername($uname)
{
    $result = false;
    if (!preg_match("/^[a-zA-Z0-9]*$/", $uname)) {
        $result = true;
    }
    return $result;
}

function invalidPassword($pwd)
{
    $result = false;
    if (!preg_match("/^[a-zA-Z0-9]*$/", $pwd) || strlen($pwd) < 8) {
        $result = true;
    }
    return $result;
}

function invalidEmail($email)
{
    $result = false;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result = true;
    }
    return $result;
}

function passwordMismatch($pwd, $pwdRepeat)
{
    $result = false;
    if ($pwd !== $pwdRepeat) {
        $result = true;
    }
    return $result;
}

function userExists($conn, $uname, $email)
{
    $result = false;
    $sql = "SELECT * FROM users WHERE username = :uname_bv OR user_email = :email_bv";
    $s = oci_parse($conn, $sql);
    oci_bind_by_name($s, ":uname_bv", $uname, -1);
    oci_bind_by_name($s, ":email_bv", $email, -1);
    oci_execute($s);
    oci_fetch_all($s, $res, 0, -1, OCI_FETCHSTATEMENT_BY_ROW);
    if (count($res)) {
        $result = true;
    }
    return $result;
}

function createUser($conn, $uname, $email, $pwd)
{
    $sql = "INSERT INTO users 
                (username, password_hash, user_email)
            VALUES
                (:uname_bv, :phash_bv, :email_bv)";
    $s = oci_parse($conn, $sql);
    oci_bind_by_name($s, ":uname_bv", $uname, -1);
    oci_bind_by_name($s, ":email_bv", $email, -1);
    $phash = md5($pwd);
    oci_bind_by_name($s, ":phash_bv", $phash, -1);
    oci_execute($s);
}

function loginUser($conn, $uname, $pwd)
{
    $sql = "SELECT * FROM users WHERE username = :uname_bv AND password_hash = :phash_bv";
    $s = oci_parse($conn, $sql);
    oci_bind_by_name($s, ":uname_bv", $uname, -1);
    $phash = md5($pwd);
    oci_bind_by_name($s, ":phash_bv", $phash, -1);
    oci_execute($s);
    oci_fetch_all($s, $res, 0, -1, OCI_FETCHSTATEMENT_BY_ROW);

    if (count($res) === 0) {
        header("location: ../login.php?error=badcreds");
        exit();
    }

    session_start();
    $_SESSION["userid"] = $res[0]["USER_ID"];
    $_SESSION["username"] = $res[0]["USERNAME"];

    header("location: ../index.php");
    exit();
}

function validQuiz($lesson_name)
{
    $conn = oci_pconnect("SYSTEM", "password", "192.168.1.167/XE");
    $user_id_bv = $_SESSION["username"];
    $lesson_name_bv = $lesson_name;
    $sql = "SELECT * FROM COMPLETE_LESSON WHERE USER_ID = :user_id_bv AND LESSON_NAME = :lesson_name_bv";
    $s = oci_parse($conn, $sql);
    oci_bind_by_name($s, ":user_id_bv", $user_id_bv, -1);
    oci_bind_by_name($s, ":lesson_name_bv", $lesson_name_bv, -1);
    oci_execute($s);
    oci_fetch_all($s, $res, 0, -1, OCI_FETCHSTATEMENT_BY_ROW);
    if (count($res) === 0) {
        header("location: ./lesson_" . $lesson_name . ".php");
        exit();
    }
}

function logQuiz($quiz_id, $avg)
{
    $conn = oci_pconnect("SYSTEM", "password", "192.168.1.167/XE");

    // Log results in DB
    $sql = "INSERT INTO QUIZ_RESULT(QUIZ_ID, USER_ID, QUESTION1, QUESTION2, QUESTION3, QUESTION4) 
            VALUES (:quiz_id, :user_id :avg)";
    $s = oci_parse($conn, $sql);
    oci_bind_by_name($s, ":quiz_id", $quiz_id, -1);
    oci_bind_by_name($s, ":user_id", $_SESSION["USER_ID"], -1);
    oci_bind_by_name($s, ":avg", $avg, -1);
    oci_execute($s);
}

function scoreQuiz($quiz_id, $q1, $q2, $q3, $q4)
{
    $conn = oci_pconnect("SYSTEM", "password", "192.168.1.167/XE");
    
    // Fetch quiz
    $sql = "SELECT * FROM QUIZ WHERE QUIZ_ID = :quiz_id";
    $s = oci_parse($conn, $sql);
    oci_bind_by_name($s, ":user_id_bv", $quiz_id, -1);
    oci_execute($s);
    oci_fetch_all($s, $res, 0, -1, OCI_FETCHSTATEMENT_BY_ROW);

    // Compare results
    $qs = $res[0];
    $q1res = ($qs["QUESTION1"] == $q1) ? 1 : 0;
    $q2res = ($qs["QUESTION2"] == $q2) ? 1 : 0;
    $q3res = ($qs["QUESTION3"] == $q3) ? 1 : 0;
    $q4res = ($qs["QUESTION4"] == $q4) ? 1 : 0;

    return ($q1res + $q2res + $q3res + $q4res) / 4;
}