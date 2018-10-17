<?php

class Posts extends Controller {

    public function __construct() {
        parent::__construct();
        require_once $_ENV['dir_models'] . 'model.post.php';
        $this->db = new Model_Post();
    }


    public function index() {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $this->getPosts();
                break;

            case 'POST':
                // Get google id token.
                if (!isset($_POST['id_token'])) {
                    http_response_code(400); return;
                }
                $googleUser = App::validateGoogleIdToken($_POST['id_token']);
                if (is_null($googleUser)) {
                    http_response_code(400); return;
                }
                $user = $this->userDb->getUser($googleUser);
                
                $this->addPost($user);
                break;
        }
    }


    private function getPosts() {
        $count = 10;
        $offest = 0;
        if (isset($_GET['count'])) {
            $count = $_GET['count'];
        }
        if (isset($_GET['offset'])) {
            $offest = $_GET['offset'];
        }

        $values = array();

        $results = $this->db->getNewestPosts($count, $offest);
        foreach ($results as $res) {
            array_push(
                $values,
                array(
                    'title' => $res['title'],
                    'body' => $res['body'],
                    'date' => $res['date'],
                    'author' => $res['author']
                )
            );
        }
        
        
        echo json_encode($values);
    }


    private function addPost($user) {
        // Check user is not banned.
        if ($user['banned'] != 0) {
            http_response_code(400); return;
        }
        // Check user is an admin.
        if ($user['admin'] == 0) {
            http_response_code(400); return;
        }
        // Get title as post parameter. (required)
        if (!isset($_POST['title'])) {
            http_response_code(400); return;
        }
        $title = $_POST['title'];
        // Get body as post parameter. (optional)
        $body = '';
        if (isset($_POST['body'])) {
            $body = $_POST['body'];
        }

        // Attempt to add post.
        $success = $this->db->addPost($title, $body, $user['id']);
        if ($success) {
            http_response_code(201);
        } else {
            http_response_code(500);
        }
    }

}