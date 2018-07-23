<?php
session_start();

echo '<!DOCTYPE html><html><head><title>Gym Booking</title>';
echo '<link rel="stylesheet" type="text/css" href="gymStyle.css" title="gymStyle">';
echo '</head><body>';
echo '<h1>Gym Booking</h1>';


// sets error reporting on or off
if (TRUE) {
  error_reporting( E_ALL );
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
}


// variables for creating queries
$db_hostname = "mysql";
$db_database = "m7jw2";
$db_username = "m7jw2";
$db_password = "seagulls";
$db_charset = "utf8mb4";

$dsn = "mysql:host=$db_hostname;dbname=$db_database;charset=$db_charset";
$opt = array(
  PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
);

$availableSpace = true;

try {
  // query DBMS
  $pdo = new PDO($dsn,$db_username,$db_password,$opt);
  $stmt = $pdo->query("SELECT * FROM classes WHERE capacity > 0");
  
  // turn PDO object into array of query result and store for session
  $classesTable = $stmt->fetchAll();
  
 

  
  if ($stmt->rowcount() > 0){
    // create an array of unique classnames from query table 
    $classNames[] = $classesTable[0]["className"];
    foreach($classesTable as $row){
      $unique = true;
      foreach($classNames as $name){
        if ($name == $row['className'])
          $unique = false;
      }
      if ($unique)
        $classNames[] = $row['className'];
    }
  } else {
    $availableSpace = false;
  }
  
  
} catch (PDOException $e){
    exit("PDO Error:".$e->getMessage()."<br>");
}


// store options in session variables
if(isset($_POST['times']))
  $_SESSION['timeChoice'] = $_POST['times'];
if(isset($_POST['class']))
  $_SESSION['classChoice'] = $_POST['class'];
if(isset($_POST['clientName']))
  $_SESSION['clientName'] = $_POST['clientName'];
if(isset($_POST['phoneNumber']))
  $_SESSION['phoneNumber'] = $_POST['phoneNumber'];



if ($availableSpace){
  // form for class options
  echo '<form name="form1" method="post">';
  // create drop down box from unique class names
  echo 'Classes: <br>';
  echo '<select name="class" onchange="document.form1.submit()">';
  echo '<option value="blank"></option>';
  foreach($classNames as $cName) {
    echo '<option value="',  $cName, '" ';
      // once an item has been chosen use selected="selected"
      if (isset($_SESSION['classChoice']))
        if ($_SESSION['classChoice'] == $cName) echo 'selected="selected"';
      echo '>',  $cName, '</option>';
  }
  echo '</select>';
  //end of class select
  
  //option for class times
  echo '<br> Times: <br>';
  echo '<select name="times" onchange="document.form1.submit()">';
  echo '<option value="blank"></option>';
  if(isset($_SESSION['classChoice'])) {
    foreach($classesTable as $row) {
      if($row["className"] == $_SESSION['classChoice']) {
        echo '<option value="',  $row["classTime"], '" ';
          // use selected="selected" to keep option visible
          if (isset($_SESSION['timeChoice']))
            if ($_SESSION['timeChoice'] == $row["classTime"]) echo 'selected="selected"';
          echo '>',  $row["classTime"], '</option>';
      }
    }   
  }
  echo '</select></form>';


  // name and phone number form
    echo '<form action="gym.php" method="post">';
    echo "Enter name:<br>";
    echo '<input type="text" name="clientName"';
    if(isset($_SESSION['clientName'])) {
        echo ' value="', $_SESSION['clientName'],'"';
    }
    echo '><br>';
    echo "Enter phone number: <br>";
    echo '<input type="text" name="phoneNumber"'; 
    if(isset($_SESSION['phoneNumber']))
        echo ' value="', $_SESSION['phoneNumber'],'"';
    echo '><br><br>';
    echo '<input type="submit" name="submit" value="submit">';
    echo '</form>';
}
else
    echo "There are no spaces in any of the classes!!!";




// create query and hit the DBMS
$checkSlots = $pdo->prepare("SELECT classID FROM classes WHERE className=? AND classTime=? AND capacity > 0");

$takeslots = $pdo->prepare("UPDATE classes SET capacity=capacity - 1 WHERE className=? AND classTime=?");

$addBookingInfo = $pdo->prepare("INSERT INTO bookings (classID, name, phoneNum) VALUES (?,?,?)");


// submit pressed, check other data present
// submit pressed, check other data present
if (isset($_POST['submit'])) {
    if (isset($_SESSION['classChoice']) && isset($_SESSION['timeChoice']) &&
    ($_SESSION['clientName'] != "" ) && ($_SESSION['phoneNumber'] != "")) {
        // remove white space from number
        $_SESSION['phoneNumber'] = minusWhiteSpace($_SESSION['phoneNumber']);
        

        
        //check phone number and name to see if correct
        if (checkPhoneNum($_SESSION['phoneNumber']) && !doubleHyphenOrApos($_SESSION['clientName']) && isWord($_SESSION['clientName'])) {
            try {
                // check for slots, $testQuery is 1 if statement executes
                // $checkSlots->fetch() gives you the resulting set
                $testQuery = $checkSlots->execute(array($_SESSION['classChoice'],
                $_SESSION['timeChoice']));
                $sessionID = $checkSlots->fetch();


                // test if result is empty, i.e. no places left. Check to see if booking just been taken.
                if ($sessionID["classID"]){
                    // take spot: capacity - 1
                    $takeslots->execute(array($_SESSION['classChoice'], $_SESSION['timeChoice']));
                    // add details to database
                    $addBookingInfo->execute(array($sessionID["classID"],$_SESSION['clientName'],
                        $_SESSION['phoneNumber']));
                    echo "Your place is booked";
                    
                    // remove class and time selection once booked
                    unset($_SESSION['timeChoice']);
                    unset($_SESSION['classChoice']);
                    
                    
                }
                else
                    echo "Sorry, the last place has just been taken";
                    // remove class and time selection if just taken
                    unset($_SESSION['timeChoice']);
                    unset($_SESSION['classChoice']);


            }
            catch (PDOException $e) {
                exit("PDO Error:".$e->getMessage()."<br>");
            }
            
        }
        else
            echo "Error. Your details have not been entered correctly!";
    }
    else
        echo "Error. You have not filled in all of the details.";
} 

// functions to check phone number and name
// http://php.net/manual/en/function.preg-replace.php
// http://php.net/manual/en/function.preg-match.php
// returns phone minus whitespace
function minusWhiteSpace($tocheck){
    $minusWS = preg_replace('/\s+/', '', $tocheck);
    return $minusWS;
}

// check phone number once white space removed
function checkPhoneNum($tocheck){
    $isPhoneNum = preg_match('/^0\d{8,9}\Z/', $tocheck);
    return $isPhoneNum;
}

// check name
function doubleHyphenOrApos($toCheck){
    return preg_match("/(--|'')/", $toCheck);
}

function isWord($toCheck){
    return preg_match("/^[-'a-zA-Z\s]+\Z/", $toCheck);
}
?>

</body>
</html>