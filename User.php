<?php

namespace Lifyzer\Api;

use PDO;
use stdClass;

class User
{
    public const LOGIN_ACTION = 'Login';
    public const REGISTRATION_ACTION = 'Registration';
    public const CHANGE_PASSWORD_ACTION = 'ChangePassword';
    public const FORGOT_PASSWORD_ACTION = 'ForgotPassword';
    public const EDIT_PROFILE_ACTION = 'EditProfile';
    public const DELETE_ACCOUNT_ACTION = 'DeleteAccount';
    public const DATA_TAKEOUT = 'TakeOut';

    private const DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const CSV_TAKEOUT_HEADER = 'user id,email,name,profile created date,profile modified date,terms acceptance date';
    private const FORGOT_PASSWORD_LENGTH = 10;

    /** @var PDO */
    protected $connection;

    public function __construct(PDO $con)
    {
        $this->connection = $con;
    }

    public function callService($service, $postData)
    {
        switch ($service) {
            case self::LOGIN_ACTION:
                return $this->login($postData);

            case self::REGISTRATION_ACTION:
                return $this->registration($postData);

            case self::CHANGE_PASSWORD_ACTION:
                return $this->changePassword($postData);

            case self::EDIT_PROFILE_ACTION:
                return $this->editProfile($postData);

            case self::FORGOT_PASSWORD_ACTION:
                return $this->forgotPassword($postData);

            case self::DELETE_ACCOUNT_ACTION:
                return $this->deleteAccount($postData);

            case self::DATA_TAKEOUT:
                return $this->dataTakeOut($postData);

            default:
                return null;
        }
    }

    private function registration($userData)
    {
        $connection = $this->connection;

        $email_id = validateObject($userData, 'email_id', "");
        $email_id = addslashes($email_id);

        $password = validateObject($userData, 'password', "");
        $password = addslashes($password);
        $password = encryptPassword($password);

        $first_name = validateObject($userData, 'first_name', "");
        $first_name = addslashes($first_name);

        $last_name = validateObject($userData, 'last_name', "");
        $last_name = addslashes($last_name);

        $device_type = validateObject($userData, 'device_type', "");
        $device_type = addslashes($device_type);

        $device_token = validateObject($userData, 'device_token', "");
        $device_token = addslashes($device_token);

        $posts = [];
        $is_delete = IS_DELETE;
        $created_date = date(self::DATETIME_FORMAT);

        $objUser = getSingleTableData($connection, TABLE_USER, "", "id", "", ['email' => $email_id, 'is_delete' => $is_delete]);
        if (!empty($objUser)) {
            $status = FAILED;
            $message = EMAIL_ALREADY_EXISTS;
        } else {

            //****  INSERT USER ****//
            $security = new Security($connection);
            $generate_guid = $security->generateUniqueId();
            $user_array = ['email' => $email_id, 'first_name' => $first_name, 'last_name' => $last_name,
                'password' => $password, 'device_type' => $device_type, 'device_token' => $device_token, 'created_date' => $created_date, 'guid' => $generate_guid];
            $user_response = addData($connection, "Registration", TABLE_USER, $user_array);

            if ($user_response[STATUS_KEY] === SUCCESS) {
                $user_inserted_id = $user_response[MESSAGE_KEY];
                $getUser = getSingleTableData($connection, TABLE_USER, "", "*", "", ['id' => $user_inserted_id, 'is_delete' => $is_delete]);
                if (!empty($getUser)) {
                    $tokenData = new stdClass;
                    $tokenData->GUID = $getUser['guid'];
                    $tokenData->userId = $getUser['id'];
                    $user_token = $security->updateTokenForUser($tokenData);
                    if ($user_token[STATUS_KEY] == SUCCESS) {
                        $data[USERTOKEN] = $user_token[USERTOKEN];
                    }
                    $posts[] = $getUser;
                    $status = SUCCESS;
                    $message = REGISTRATION_SUCCESSFULLY_DONE;
                } else {
                    $status = FAILED;
                    $message = DEFAULT_NO_RECORD;
                }
            } else {
                $status = FAILED;
                $message = SOMETHING_WENT_WRONG_TRY_AGAIN_LATER;
            }
        }
        $data['status'] = $status;
        $data['message'] = $message;
        $data['User'] = $posts;

        return $data;
    }

    private function editProfile($userData)
    {
        $connection = $this->connection;

        $user_id = validateObject($userData, 'user_id', "");
        $user_id = addslashes($user_id);

        $email_id = validateObject($userData, 'email_id', "");
        $email_id = addslashes($email_id);

        $first_name = validateObject($userData, 'first_name', "");
        $first_name = addslashes($first_name);

        $is_delete = IS_DELETE;

        $posts = [];

        $objUserEmail = getSingleTableData($connection, TABLE_USER, "", "*", " id != $user_id ", ['email' => $email_id, 'is_delete' => $is_delete]);
        if (!empty($objUserEmail)) {
            $created_date = getDefaultDate();
            $update_array = ['first_name' => $first_name, 'email' => $email_id, 'modified_date' => $created_date];

            $edit_response = editData($connection, "UpdateProfile", TABLE_USER, $update_array, ['id' => $user_id]);
            if ($edit_response[STATUS_KEY] === SUCCESS) {
                $getUser = getSingleTableData($connection, TABLE_USER, "", "*", "", ['id' => $user_id, 'is_delete' => $is_delete]);
                if (!empty($getUser)) {
                    $posts[] = $getUser;
                }
                $status = SUCCESS;
                $message = PROFILE_UPDATED_SUCCESSFULLY;
            } else {
                $status = FAILED;
                $message = SOMETHING_WENT_WRONG_TRY_AGAIN_LATER;
            }
        } else {
            $status = FAILED;
            $message = EMAIL_ALREADY_EXISTS;
        }
        $data['status'] = $status;
        $data['message'] = $message;
        $data['User'] = $posts;
        return $data;
    }

    private function changePassword($userData)
    {
        $connection = $this->connection;

        $user_id = validateObject($userData, 'user_id', "");
        $user_id = addslashes($user_id);

        $password = validateObject($userData, 'password', "");
        $password = addslashes($password);
        $password = encryptPassword($password);

        $new_password = validateObject($userData, 'new_password', "");
        $new_password = addslashes($new_password);
        $new_password = encryptPassword($new_password);

        $is_delete = IS_DELETE;
        $posts = [];

        $objUser = getSingleTableData($connection, TABLE_USER, "", "id,password", "", ['id' => $user_id, 'is_delete' => $is_delete]);
        if (!empty($objUser)) {
            if ($password === $objUser['password']) {
                $created_date = getDefaultDate();
                $edit_response = editData($connection, "Login", TABLE_USER, ['password' => $new_password, 'modified_date' => $created_date], ['id' => $user_id]);
                if ($edit_response[STATUS_KEY] === SUCCESS) {
                    $getUser = getSingleTableData($connection, TABLE_USER, "", "*", "", ['id' => $user_id, 'is_delete' => $is_delete]);
                    if (!empty($getUser)) {
                        $posts[] = $getUser;
                        $status = SUCCESS;
                        $message = PASSWORD_CHANGED;
                    } else {
                        $status = FAILED;
                        $message = DEFAULT_NO_RECORD;
                    }
                } else {
                    $status = FAILED;
                    $message = SOMETHING_WENT_WRONG_TRY_AGAIN_LATER;
                }
            } else {
                $status = FAILED;
                $message = CHNG_WRONG_PASSWORD_MESSAGE;
            }
        } else {
            $status = FAILED;
            $message = NO_DATA_AVAILABLE;
        }
        $data['status'] = $status;
        $data['message'] = $message;
        $data['User'] = $posts;

        return $data;
    }

    private function login($userData)
    {
        $connection = $this->connection;

        $email_id = validateObject($userData, 'email_id', "");
        $email_id = addslashes($email_id);

        $password = validateObject($userData, 'password', "");
        $password = addslashes($password);

        $device_type = validateObject($userData, 'device_type', "");
        $device_type = addslashes($device_type);

        $posts = [];

        $is_delete = IS_DELETE;

        $token = '';

        $objUser = getSingleTableData($connection, TABLE_USER, '', "*", "", ['email' => $email_id, 'is_delete' => $is_delete]);
        if (!empty($objUser)) {
            if (encryptPassword($password) === $objUser['password']) {

                $user_id = $objUser['id'];
                $created_date = getDefaultDate();
                $edit_response = editData($connection, "Login", TABLE_USER, ['device_type' => $device_type, 'modified_date' => $created_date], ['id' => $user_id]);
                if ($edit_response[STATUS_KEY] == SUCCESS) {
                    if ($objUser['guid'] == null || $objUser['guid'] == "") {
                        $generate_user_guid = $this->updateGuidForUser($user_id);
                    } else {
                        $generate_user_guid = $objUser['guid'];
                    }
                    $tokenData = new stdClass;
                    $tokenData->GUID = $generate_user_guid;
                    $tokenData->userId = $user_id;
                    $security = new Security($connection);
                    $user_token = $security->updateTokenforUser($tokenData);

                    if ($user_token[STATUS_KEY] === SUCCESS) {
                        $token = $user_token[USERTOKEN];
                    }
                    $objUser['device_type'] = $device_type;
                    $objUser['modified_date'] = $created_date;
                    $posts[] = $objUser;
                    $status = SUCCESS;
                    $message = USER_LOGIN_SUCCESSFULLY;
                    $data[USERTOKEN] = $token;
                } else {
                    $status = FAILED;
                    $message = SOMETHING_WENT_WRONG_TRY_AGAIN_LATER;
                }
            } else {
                $status = FAILED;
                $message = WRONG_PASSWORD_MESSAGE;
            }
        } else {
            $status = FAILED;
            $message = NO_EMAIL_AND_PASSWORD_AVAILABLE;
        }

        $data['status'] = $status;
        $data['message'] = $message;
        $data['User'] = $posts;

        return $data;
    }

    private function updateGuidForUser($user_id)
    {
        $connection = $this->connection;
        $is_delete = DELETE_STATUS::NOT_DELETE;
        $objUser = getSingleTableData($connection, TABLE_USER, "", "id,guid", "", ['id' => $user_id, 'is_delete' => $is_delete]);
        if (!empty($objUser)) {
            $security = new Security($connection);
            $generate_guid = $security->generateUniqueId();
            $edit_response = editData($connection, 'UpdateGuid', TABLE_USER, ['guid' => $generate_guid], ['id' => $user_id]);
            if ($edit_response[STATUS_KEY] === SUCCESS) {
                return $generate_guid;
            }
        }

        return '';
    }

    private function forgotPassword($userData)
    {
        $connection = $this->connection;

        $email_id = validateObject($userData, 'email_id', "");
        $email_id = addslashes($email_id);

        $is_delete = IS_DELETE;

        $objUser = getSingleTableData($connection, TABLE_USER, "", "id,first_name", "", ['email' => $email_id, 'is_delete' => $is_delete]);
        if (!empty($objUser)) {
            $email = new Email();
            $userPassword = generateRandomString(self::FORGOT_PASSWORD_LENGTH);
            $dbPassword = encryptPassword($userPassword);
            $created_date = getDefaultDate();

            $edit_response = editData($connection, 'Forgot Password', TABLE_USER, ['password' => $dbPassword, 'modified_date' => $created_date], ['email' => $email_id]);
            if ($edit_response[STATUS_KEY] === SUCCESS) {

                $appname = APPNAME;
                $firstname = $objUser['first_name'];
                $lastname = '';

                $message = '<html><body>
                              <p>Hi ' . $firstname . ' ' . $lastname . ',</p>
                              <p>Your new password for ' . $appname . ' account is :</br>
                                  password: ' . $userPassword . '</p>
                              <p>Regards,</br>
                              ' . $appname . ' Team</p>
                              </body></html>';

                $email->send(SENDER_EMAIL_ID, $message, 'Forgot Password', $email_id);
                $status = SUCCESS;
                $message = PASSWORD_SENT;
            } else {
                $status = FAILED;
                $message = SOMETHING_WENT_WRONG_TRY_AGAIN_LATER;
            }
        } else {
            $status = FAILED;
            $message = NO_DATA_AVAILABLE;
        }
        $data['status'] = $status;
        $data['message'] = $message;

        return $data;
    }

    private function deleteAccount(array $userData): array
    {
        $email_id = validateObject($userData, 'email_id', '');

        $sqlQuery = 'DELETE FROM %s WHERE email = :emailId LIMIT 1';
        $stmt = $this->connection->prepare(
            sprintf($sqlQuery, TABLE_USER)
        );
        $stmt->bindValue(':emailId', $email_id);

        if ($stmt->execute()) {
            $status = SUCCESS;
            $message = 'Account successfully deleted';
        } else {
            $status = FAILED;
            $message = SOMETHING_WENT_WRONG_TRY_AGAIN_LATER;
        }

        $data['status'] = $status;
        $data['message'] = $message;

        return $data;
    }

    /**
     * Data takeout feature. Ideal to be GDPR compliant.
     *
     * @param array $userData
     *
     * @return array
     */
    private function dataTakeOut(array $userData): array
    {
        $email_id = validateObject($userData, 'email_id', '');

        $sqlQuery = <<<QUERY
SELECT u.email, u.first_name, u.user_image, u.created_date, u.modified_date, h.*, f.* 
FROM %s AS u INNER JOIN %s AS h ON u.id = h.user_id 
INNER JOIN %s AS f ON u.id = f.user_id
WHERE u.email = :emailId
QUERY;

        $stmt = $this->connection->prepare(
            sprintf(
                $sqlQuery,
                TABLE_USER,
                TABLE_HISTORY,
                TABLE_FAVOURITE
            )
        );
        $stmt->bindValue(':emailId', $email_id);
        $stmt->execute();

        $data = [];
        $dbData = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['data'] = $this->buildCsvDataFormat($dbData);

        if (!empty($data)) {
            $status = SUCCESS;
            $message = 'You full data have been exported!';
        } else {
            $status = FAILED;
            $message = NO_DATA_AVAILABLE;
        }

        $data['status'] = $status;
        $data['message'] = $message;

        return $data;
    }

    private function buildCsvDataFormat(array $data): string
    {
        $csvData = self::CSV_TAKEOUT_HEADER;
        $csvData .= implode(',', $data);

        return $csvData;
    }
}