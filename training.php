<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
	<title>IT Training - Booking Page</title>
  <meta name="Training booking page" content="A webpage in which booking requests are sent for University IT training sessions">
  <link rel="stylesheet" href="training.css">
 
</head>
<body>
	<h1>Training Session - Booking page</h1>

<?php
//I start a session here
session_start();
//
error_reporting( E_ALL );
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//these are the Database credentials
$servername = "studdb.csc.liv.ac.uk";
$username = "sgafarr3";
$password = "database";
$dbname = "sgafarr3";


// here is the PDO object I use to query and fetch data from the sql database
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    

    // check if there are still avalaible training sessions 
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ClassTimes WHERE capacity > 0");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['COUNT(*)'] > 0) {
            try {
                // here i beign a transaction, ensuring that if two people try to book at the same time, and one is rolled back, they'll come back to this spot
                $pdo->beginTransaction();

                //here is the code that lets you select a topic from a dropdown menu and then generates the availabletimes for that topic
                // first I  get the topics from the database and list them with a prepared statement
                $stmt = $pdo->query("SELECT DISTINCT Topic FROM ClassTimes WHERE Capacity > 0 ORDER by Topic ASC FOR UPDATE");
                $topics = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // if the topic has been selected, we proceed
                if (isset($_POST['topic'])) {
                    // Save the selected topic in a session variable
                    $_SESSION['selected_topic'] = $_POST['topic'];
                }

                // Get the selected topic from the session variable
                $selected_topic = $_SESSION['selected_topic'] ?? null;

                // Here we query the available times for the selected topic
                //we have the prepared statement used to get  the time slots
                $stmt = $pdo->prepare("SELECT freeslot FROM ClassTimes WHERE Topic = :topic AND Capacity > 0 ORDER BY STR_TO_DATE(freeslot, '%W, %H:%i')");
                $stmt->bindParam(':topic', $selected_topic);
                $stmt->execute();
                $times = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // The following is the actual forms visible to the user
                echo "
                <form name='form1' method='post'>
                <select name='topic' onChange='document.form1.submit()'>
                <option value='None'>Select a topic</option>";
                
                foreach ($topics as $topic) {
                    $selected = ($topic == $selected_topic) ? 'selected' : '';
                    echo "<option value='$topic' $selected>$topic</option>";
                }

                echo "</select>
                </form>";

                echo "
                <form name='form2' method='post'>
                <input type='hidden' name='topic' value='$selected_topic'>
                <select name='time'>
                <option value='None'>Select a time</option>";
                foreach ($times as $time) {
                    echo "<option value='$time'>$time</option>";
                }
                
                //Here is the section where, if a submission failed, we check each form to see if it was an invalid submission and, if so, we clear it.
                $nameRegex = "/^(?!.*([-']{2,}))[a-zA-Z']{1}[a-zA-Z-' ]+[^- ]+$/";
                if (!preg_match($nameRegex, $_POST['name'])) {
                  echo "</select>
                <br><br>
                <input type='text' name='name' id ='name' placeholder = 'Please enter your name' > ";
                }

                // else fill the form in with valid submission from last attempt  
                else {
                  echo " </select>
                  <br><br>
                  <input type='text' name='name' id ='name' placeholder = 'Please enter your name' value = ".$_POST['name']." > ";
                }

              //email hasnt been initliased at the first use of the webpage, so this just assigns it a blank variable so that it doesnt throw an error.
              if (!array_key_exists('email', $_POST)) {
                $_POST['email'] = '';
              }

              // check to see if name was invalid last submission, and clear it if so
              $emailRegex = "/^[a-z._-]+[^-.]+@[a-z._-]+[^-.]+$/";
              if (!preg_match($emailRegex, $_POST['email'])) {
                  echo "
                <br><br>
                <input type='text' name='email' id ='email' placeholder = 'Please enter your email' > <br> <br>";
                }

              // else fill the form in with valid submission from last attempt 
              else {
                echo "
                <br><br>
                <input type='text' name='email' id ='email' placeholder = 'Please enter your email' value = ".$_POST['email']." > <br> <br>";
              }

  
                // creates a submit buton that will take all the info in
                echo "<button type='submit' name='submit'>Book Now</button>
                </form>";

                
                if (isset($_POST['submit'])){
                    $topic = $_POST['topic'];
                    $time = $_POST['time'];
                    $name = $_POST['name'];
                    $email = $_POST['email'];


                    
                    if ($topic == 'None' && $time == 'None')  {

                        echo "Please make sure that you enter a Topic and Time";
                        exit;
                    }
                    if ($topic == 'None') {

                        echo "Please make sure that you enter a Topic";
                        exit;
                    }

                    if ($time == 'None') {

                        echo "Please make sure that you enter a Time";
                        exit;
                    }



                   
                    // Validate name
                    $nameRegex = "/^(?!.*([-']{2,}))[a-zA-Z']{1}[a-zA-Z-' ]+[^- ]+$/";
                    if (!preg_match($nameRegex, $name)) {
                        echo "Invalid name entered!";

                        exit;
                    }
                    
                    
                    // Validate email
                    $emailRegex = "/^[a-z._-]+[^-.]+@[a-z._-]+[^-.]+$/";
                    if (!preg_match($emailRegex, $email)) {
                        echo "Invalid email address entered!";

                        exit;
                    }
                
                    // Store data in an array
                    $data = array(
                        'topic' => $topic,
                        'time' => $time,
                        'name' => $name,
                        'email' => $email
                    );
                

                // Prepare and execute the SELECT statement
                $stmt = $pdo->prepare("SELECT Capacity FROM ClassTimes WHERE Topic = :topic AND freeslot = :timeSlot");
                $stmt->execute(array(':topic' => $data['topic'], ':timeSlot' => $data['time']));

                // Fetch the result
                $result = $stmt->fetch();

                // Check if capacity is greater than 0
                if ($result && $result['Capacity'] > 0) {
                    echo "";
                } else {
                    echo "Sorry, there are no spaces left for ".$data['topic']." at ".$data['time']."!";
                    exit;
                }

                // Prepare the INSERT statement
                $stmt = $pdo->prepare("INSERT INTO bookings (bookingid, Topic, freeslot, clientname, email) VALUES (:id, :topic, :freeslot, :clientname, :email)");

                // Generate a new ID for the booking
                $id = rand(10000, 99999);

                // Execute the statement with the data from the $data array
                $stmt->execute(array(':id' => $id, ':topic' => $data['topic'], ':freeslot' => $data['time'], ':clientname' => $data['name'], ':email' => $data['email']));

                // Prepare the UPDATE statement to decrement the capacity by 1
                $stmt = $pdo->prepare("UPDATE ClassTimes SET Capacity = Capacity - 1 WHERE Topic = :topic AND freeslot = :timeSlot");

                // Execute the statement with the data from the $data array
                $stmt->execute(array(':topic' => $data['topic'], ':timeSlot' => $data['time']));

                // Output a success message
                echo "Booking added successfully! <br> <br>";

                echo "Topic: " . $topic . "<br>";
                echo "Time: " . $time . "<br>";
                echo "Name: " . $name . "<br>";
                echo "Email: " . $email . "<br>";







            }


            $pdo->commit();
            }
            catch (PDOException $e) {
                $pdo->rollBack();
                throw $e;
            }
            }
    else {echo '<div class="big-message">';
      echo ' Sorry, it appears that there are no further slots available for training. <br> Have a nice day!';
      echo '</div>';
    }

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>


</body>
</html>
