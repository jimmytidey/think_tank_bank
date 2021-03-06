<?
/*
 * twitter_interactions
 *
 * Takes a list of Twitter IDs and checks their tweets for relevant information 
 *
 * @people (array) DB results of a query for people or aliens 
 * @connection (object) Pass in the Twitter connection object
 * @db (object) Pass in the DB connection object 
 * @is_alien (int) Distinguishes between aliens and non-aliens when saving to the tweets table 
 * @return (array) List of all the tweets found 
 */

function twitter_interactions($people, $connection, $db, $is_alien) {
    
    $return = array();
    
    foreach ($people as $person) {
                
        if ($is_alien) { 
            $name = $person['name'];
        }
        
        else { 
            $name = $person['name_primary'];
        }    
        echo "<h2>" . $name . "</h2>";
        
        $tweets = $connection->get('statuses/user_timeline', array(
            'user_id' => $person['twitter_id'],
            'include_rts' => 'true'
        ));
        
        print_r($tweets);
        
        
        if (count($tweets) == 0) {
            echo "no tweets for this user";
        }
        
        foreach ($tweets as $tweet) {
            
            $tweet_id   = $tweet->id_str;
            $text       = addslashes($tweet->text);
            $rts        = $tweet->retweet_count;
            $user_id    = $tweet->user->id;
            $time       = strtotime($tweet->created_at);
            
            if(isset($tweet->retweeted_status)) {
                echo "***";
                print_r($tweet->retweeted_status);
            
            }
            if($rts > 0  && !isset($tweet->retweeted_status)) {    
                echo "<p>-- is not retweet --</p>";
                $is_retweet = 0;
            }
            else {
                echo "<p>-- is retweet --</p>";
                $is_retweet = 1;  
            }
            
            $return[] = $tweet;
            
            echo " --> " . $text . "<-- ($tweet_id) \n\n";
                       
            //add to the tweets table     
            $existing_tweet_query = "SELECT * FROM tweets WHERE tweet_id='" . $tweet_id . "'";
            $existing_tweet       = $db->fetch($existing_tweet_query);
            
            if (count($existing_tweet) == 0) {
                echo "INSERTING TWEET  \n\n";
                $query = "INSERT INTO tweets (tweet_id, text, rts, user_id, `time`, is_alien, is_rt) VALUES ('$tweet_id', '$text','$rts','$user_id', '$time', '$is_alien', '$is_retweet')";
                $db->query($query);

                //now test if there are any URLS to monitor 
                
             
                if(is_array($tweet->entities->urls)) {
                    
                    foreach($tweet->entities->urls as $url) {
                        echo "--------------------------------------------";
                        
                        $clean_url = $url->url;  
                        if (is_object($url)) {
                            $expanded_url = $url->expanded_url; 
                            print_r($url->expanded_url); 
                        }
                        $query = "INSERT INTO links  (twitter_id, url, expanded_url, `time`, tweet_id) VALUES ('$user_id', '$clean_url', '$expanded_url', '$time', '$tweet_id') ";
                        
                        $db->query($query);
                    }    
                }    
            }
            
            else {
                echo "UPDATING TWEET \n\n";
                $query = "UPDATE tweets SET text='$text', rts='$rts', user_id='$user_id', is_alien='$is_alien', is_rt='$is_retweet' WHERE tweet_id='$tweet_id' ";
                echo $query; 
                $db->query($query);
            }
            
            //If this tweet mentions another user who is in our system, it needs to be added to the mentions table
            $words = explode(" ", $tweet->text);
            
            //detect retweet
            if (is_numeric(strpos($tweet->text, 'RT '))) {
                $retweet_status = 1;
                echo "RT detected<br/>";
            }
             
            else {
                $retweet_status = 0;
            }
            
            //detect user names 
            $users = array();
            $i     = 0;
                        
            foreach ($words as $word) {
                if (preg_match("/@/", $word)) {
                    $user = str_replace("@", "", $word);
                    $user = explode("'", $user);
                    if (!empty($user)) {
                        $users[] = $user[0];
                    }
                }
                $i++;  
            }
            
            //check to see if the user names are listed in the DB (ignore aliens, we only care about mentions that point towards think tankers)
            foreach ($users as $user) {
               
                //try to find the person in the thinktanks list
                $match_query = "SELECT * FROM people WHERE twitter_handle='$user'";
                $matches = $db->fetch($match_query);
                
                //otherwise try the aliens list 
                if (count($matches)==0) { 
                    $query= "SELECT * FROM aliens WHERE twitter_handle='$user'";
                    $match_query = "SELECT * FROM aliens WHERE twitter_handle='$user'";
                    echo $match_query . "/n";  
                    $matches = $db->fetch($match_query);
                }
                
                
                if (count($matches) > 0) {
                    
                    if(isset($matches[0]['name_primary'])) { 
                        $target_name   = $matches[0]['name_primary'];
                    }
                    else { 
                        $target_name   = $matches[0]['name'];
                    }
                    
                    $target_id     = $matches[0]['twitter_id'];
                 
                    $tweet_id      = $tweet->id_str;
                    $originator_id = $person['twitter_id'];
                    
                    $existing_query = "SELECT * FROM people_interactions WHERE tweet_id ='$tweet_id' && target_id='$target_id'";
               
                    $existing = $db->fetch($existing_query);
                    
                    if (count($existing) == 0 && $target_id != $originator_id) {
                        echo "New mention: $tweet->text for $target_name \n\n";
                        
                        $text         = addslashes($tweet->text);
                        $insert_query = "INSERT INTO people_interactions (tweet_id, originator_id, target_id, `text`, rt, `time`) VALUES ($tweet_id, $originator_id, $target_id, '$text', '$retweet_status', $time)";
                        $db->query($insert_query);
                    } else {
                        echo "Already recorded as a mention: $tweet->text for $target_name \n\n ";
                    }
                }
                else { 
                    echo "Mentions user not in the DB \n\n ";
                }
            }
            
            echo " \n\n \n\n";
        }
        echo " \n\n";
    }
    
    return $return; 
}

?>