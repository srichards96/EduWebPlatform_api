<?php

class Topics extends Controller {

    /**
     * Initializes new instace on Topics controllers.
     * Automatically gets instance of topics model.
     */
    public function __construct() {
        require_once $_ENV['dir_models'] . $_ENV['models']['topics'];
        $this->db = new Model_Topic();
    }


    /**
     * Checks whether topic with given id exists within the subject with the given id.
     * @param subjectid - subject topic is in.
     * @param topicid - id of topic.
     */
    public function checkTopicExists($subjectid, $topicid) {
        $results = $this->db->checkTopicExistsByID($subjectid, $topicid);
        if (!isset($results)) {
            return null;
        }
        return $results;
    }


    /**
     * Checks whether the subject with given id exists.
     * @param subjectid - id of subject.
     */
    public function checkSubjectExists($subjectid) {
        require_once $_ENV['dir_controllers'] . $_ENV['controllers']['subjects'];
        $subjectsController = new Subjects();
        $results = $subjectsController->checkSubjectExists($subjectid);
        if (!isset($results)) {
            return null;
        }
        return $results;
    }





    /**
     * Gets all topics within the given subject (by subject_id)
     * @param id - id of subject.
     */
    public function getAllTopicsBySubject($id) {
        // Attempt to authorize user as admin. Not required.
        $user = Auth::validateSession(true);

        // Check subject exists.
        if (!$this->checkSubjectExists($id)) {
            http_response_code(404); return;
        }


        // Get count / offset GET params if given.
        $count = App::getGETParameter('count', 10);
        $offset = App::getGETParameter('offset', 0);

        // Attempt query.
        $results = null;
        if (isset($user)) {
            // Admin. Include hidden topics.
            $results = $this->db->getTopicsBySubjectAdmin($id, $count, $offset);
        } else {
            // Not admin. Get non-hidden topics.
            $results = $this->db->getTopicsBySubject($id, $count, $offset);
        }
        // Check successful.
        if (!isset($results)) {
            http_response_code(400); return;
        }

        
        // Format and display results.
        $output = $this->formatRecords($results);
        $this->printJSON($output);
    }


    /**
     * Gets topic record with given id and given subject_id.
     * @param subjectid - subject topic is in.
     * @param topicid - id of topic.
     */
    public function getTopicByID($subjectid, $topicid) {
        // Attempt to authorize user as admin. Not required.
        $user = Auth::validateSession(true);

        // Attempt query.
        $results = null;
        if (isset($user)) {
            // Admin. Include hidden topics.
            $results = $this->db->getTopicByIDAdmin($subjectid, $topicid);
        } else {
            // Not admin. Get non-hidden topics.
            $results = $this->db->getTopicByID($subjectid, $topicid);
        }
        // Check successful.
        if (!isset($results)) {
            http_response_code(400); return;
        }
        // Check exists.
        if (sizeof($results) == 0) {
            http_response_code(404); return;
        }


        // Format and display results.
        $output = $this->formatRecords($results);
        $this->printJSON($output);
    }





    /**
     * Creates new topic record.
     * @param subjectID - id of subject.
     */
    public function createTopic($subjectID) {
        // Check user signed into a session. Require that they be an admin.
        $user = Auth::validateSession(true);
        if (!isset($user)) {
            http_response_code(401); return;
        }

        // Check JSON sent as POST param.
        if (!isset($_POST['content'])) {
            $this->printMessage('`content` parameter not given in POST body.');
            http_response_code(400); return;
        }

        // Validate JSON.
        $json = $this->validateJSON($_POST['content']);
        if (!isset($json)) {
            $this->printMessage('`content` parameter is invalid or does not contain required fields.');
            http_response_code(400); return;
        }

        // Set values.
        $name =                         $json['name'];
        $description =                  (isset($json['description'])) ? $json['description'] : '';
        $hidden =                       (isset($json['hidden'])) ? $json['hidden'] : false;
        // Convert hidden (bool) to string. (0 / 1).
        $hidden = App::boolToString($hidden);
        

        // validate values.
        $validate = $this->validateValues($name, $description);
        if (isset($validate)) {
            $this->printMessage($validate);
            http_response_code(400); return;
        }
        

        // Check subject exists.
        if (!$this->checkSubjectExists($subjectID)) {
            $this->printMessage('Specified subject does not exist.');
            http_response_code(400); return;
        }
        // Check no topic with name and subject id.
        if ($this->db->checkTopicExists($subjectID, $name)) {
            $this->printMessage('Topic with name `' . $name . '` already exists in the specified subject.');
            http_response_code(400); return;
        }

        // Attempt to create.
        $result = $this->db->addTopic($subjectID, $name, $description, $hidden);
        if (!isset($result)) {
            http_response_code(500);
            $this->printMessage('Something went wrong. Unable to add topic.');
            return;
        }

        // Get newly created resource and return it.
        $record = $this->db->getTopicByIDAdmin($subjectID, $result);
        if (!isset($record)) {
            http_response_code(500);
            $this->printMessage('Something went wrong. Topic was created, but cannot be retrieved.');
            return;
        }
        $this->printJSON($this->formatRecords($record));
        http_response_code(201);
    }





    /**
     * Modifies existing topic.
     * @param subjectid - subject the topic is within.
     * @param topicid - id of topic.
     */
    public function modifyTopic($subjectid, $topicid) {
        // Check user signed into a session. Require that they be an admin.
        $user = Auth::validateSession(true);
        if (!isset($user)) {
            http_response_code(401); return;
        }

        // Check topic exists.
        if (!$this->checkTopicExists($subjectid, $topicid)) {
            $this->printMessage('Specified topic does not exist.');
            http_response_code(404); return;
        }

        // Check JSON sent as POST param.
        if (!isset($_POST['content'])) {
            $this->printMessage('`content` parameter not given in POST body.');
            http_response_code(400); return;
        }

        // Check JSON is valid.
        $invalid = false;
        try {
            $json = json_decode($_POST['content'], true);
            if (!isset($json)) { $invalid = true; }
        } catch (Exception $e) {
            $invalid = true;
        }
        if ($invalid) {
            $this->printMessage('`content` parameter is invalid.');
            http_response_code(400); return;
        }

        // Set values.
        $name =                 (isset($json['name'])) ? $json['name'] : null;
        $description =          (isset($json['description'])) ? $json['description'] : null;
        $hidden =                   (isset($json['hidden'])) ? $json['hidden'] : null;
        // Convert hidden (bool) to string. (0 / 1).
        if (isset($hidden)) { $hidden = App::boolToString($hidden); }

        // Ensure a value is actually being changed.
        if (!isset($name) &&
            !isset($description) &&
            !isset($hidden)) {
            $this->printMessage('No fields specified to update.');
            http_response_code(400); return;
        }


        // validate values.
        $validate = $this->validateValues($name, $description);
        if (isset($validate)) {
            $this->printMessage($validate);
            http_response_code(400); return;
        }


        // Check no topic with name and subject id.
        if (isset($name) && $this->db->checkTopicExists($subjectid, $name)) {
            $this->printMessage('Topic with name `' . $name . '` already exists in the specified subject.');
            http_response_code(400); return;
        }


        // Attempt query.
        $result = $this->db->modifyTopic($topicid, $name, $description, $hidden);
        if (!isset($result)) {
            $this->printMessage('Something went wrong. Unable to update topic.');
            http_response_code(500); return;
        }

        // Get updated resource and return it.
        $record = $this->db->getTopicByIDAdmin($subjectid, $topicid);
        if (!isset($record)) {
            $this->printMessage('Something went wrong. Topic was updated, but cannot be retrieved.');
            http_response_code(500); return;
        }

        $this->printJSON($this->formatRecords($record));
        http_response_code(200);
    }





    /**
     * Deletes topic with given id and subject_id.
     * @param subjectid - subject topic is in.
     * @param topicid - id of topic.
     */
    public function deleteTopic($subjectid, $topicid) {
        // Check user signed into a session. Require that they be an admin.
        $user = Auth::validateSession(true);
        if (!isset($user)) {
            http_response_code(401); return;
        }

        // Check topic exists.
        if (!$this->checkTopicExists($subjectid, $topicid)) {
            http_response_code(404);
            $this->printMessage('Specified topic does not exist.');
            return;
        }

        // Attempt to delete.
        $result = $this->db->deleteTopic($topicid);
        if (!$result) {
            http_response_code(500);
            $this->printMessage('Something went wrong. Unable to delete topic.');
            return;
        }
        // Success.
        http_response_code(200);
    }





    /**
     * Formats records for output.
     * @param records - records to be formatted.
     */
    protected function formatRecords($records) {
        $results = array();
        foreach ($records as $rec) {
            array_push(
                $results,
                array(
                    'id' => (int)$rec['id'],
                    'name' => $rec['name'],
                    'description' => $rec['description'],
                    'hidden' => ($rec['hidden'] == '1'),
                    'lessonCount' => (int)$rec['lessonCount'],
                    'testCount' => (int)$rec['testCount']
                )
            );
        }
        return $results;
    }


    /**
     * Validates incoming JSON (for create / modify resource) so that it contains all necessary fields.
     * @param json - the json of the object.
     */
    protected function validateJSON($json) {
        // Try to parse.
        try {
            $object = json_decode($json, true);
        } catch (Exception $e) {
            return null;
        }

        // Check if has required fields.
        if (!isset($object) ||
            !isset($object['name'])) {
            return null;
        }

        // Check given fields are correct type.
        if (isset($object) &&
            isset($object['hidden']) &&
            gettype($object['hidden']) != 'boolean') {
            return null;
        }
        return $object;
    }



    /**
     * Validates values. Returns message if invalid. Returns null if valid.
     */
    protected function validateValues($name, $description) {
        // NAME
        if (isset($name)) {
            if (strlen($name) == 0) { return 'Name cannot be blank.'; }
            if (strlen($name) > 100) { return 'Name cannot be longer than 100 characters.'; }
        }
        // DESCRIPTION
        if (isset($description)) {
            if (strlen($description) > 4096) { return 'Description cannot be longer than 4096 characters.'; }
        }
        return null;
    }
}