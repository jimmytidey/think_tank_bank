<? 
include('../header.php');

//get old twitter table useful rows 

$old_people = $db->fetch("SELECT * FROM people_old WHERE twitter_handle !='' ");

foreach($old_people as $old_person) { 
    $update_query = "SELECT * FROM  people WHERE name_primary= '". addslashes($old_person['name_primary']) ."'";
    
    $result = $db->fetch($update_query);
    if (count($result) ==0) { 
        echo ($old_person['name_primary']);
    }
}


include('../footer.php');

?>