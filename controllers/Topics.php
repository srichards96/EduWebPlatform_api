<?php

class Topics extends Controller {

    public function __construct() {
        parent::__construct();
        require_once $_ENV['dir_models'] . 'model.topic.php';
        $this->db = new Model_Topic();
    }


    public function getAllTopicsBySubject($id) {
        // Validate $id.
        if (!isset($id) || !App::stringIsInt($id)) {
            http_response_code(400); return;
        }
        $id = (int)$id;

        // Get count / offset GET params if given.
        $count = 10; $offset = 0;
        if (isset($_GET['count']) && App::stringIsInt($_GET['count'])) {
            $count = (int)$_GET['count'];
        }
        if (isset($_GET['offset']) && App::stringIsInt($_GET['offset'])) {
            $offset = (int)$_GET['offset'];
        }

        // Attempt query.
        $results = $this->db->getTopicsBySubject($id, $count, $offset);
        // Check successful.
        if (!isset($results)) {
            http_response_code(400); return;
        }
        
        // Format and display results.
        $output = array();
        foreach ($results as $res) {
            array_push(
                $output,
                array(
                    'id' => (int)$res['id'],
                    'name' => $res['name'],
                    'imageUrl' => $res['imageUrl']
                )
            );
        }
        $this->printJSON($output);
    }


    public function getTopicByID($id) {
        // Validate $id.
        if (!isset($id) || !App::stringIsInt($id)) {
            http_response_code(400); return;
        }
        $id = (int)$id;

        // Attempt query.
        $results = $this->db->getTopicByID($id);
        // Check successful.
        if (!isset($results)) {
            http_response_code(400); return;
        }
        // Check exists.
        if (sizeof($results) == 0) {
            http_response_code(404); return;
        }


        // Format and display results.
        $output = array();
        foreach ($results as $res) {
            array_push(
                $output,
                array(
                    'id' => (int)$res['id'],
                    'name' => $res['name'],
                    'imageUrl' => $res['imageUrl']
                )
            );
        }
        $this->printJSON($output);
    }
}