<? 
include_once("../../ini.php");

class demosPeople extends scraperPeopleClass {
    
    function init() {
        
        //set up thinktank 
        $this->init_thinktank("Demos"); 
        if (!isset($_GET['debug'])) { 
            $people = $this->dom_query($this->base_url . '/people', '.person');
        }
        
        else { 
            $people = $this->dom_query($this->base_url, '.person');
        }    
        
        if (count($people)==0) {$this->person_scrape_read(false, $this->thinktank_id);}
        
        else {     
            $this->person_scrape_read(true, $this->thinktank_id);
            
            $i=0;
            foreach($people as $person) {
                $this->person_loop_start($i); 
                
                $name           = $this->dom_query($person['node'], 'h4 a');
                $name           = trim($name['text']);
                
                $role           = $this->dom_query($person['node'], '.job-title');
                $role           = trim($role['text']);

                $description    = $this->dom_query($person['node'], '.overview + p');
                $description    = $description['text'];

                $image  = $this->dom_query($person['node'], '.person-image');
                $image_url = $this->base_url . "/" . @$image['src']; 
                
                $start_date = time();
                $db_output = $this->db->save_job($name, $this->thinktank_id, $role, $description, $image_url, $start_date);
                $this->person_loop_end($db_output, $name, $this->thinktank_id, $role, $description, $image_url, $start_date);
                $i++;
            }
            $this->staff_left_test($this->thinktank_id);
        }
    }
}

$scraper = new demosPeople; 
$scraper->init();

?>