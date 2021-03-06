<?php
class RegisterController extends ControllerBase
{
    public function initialize()
    {
        parent::initialize();

        // Never cache the served page
        $this->response->setHeader("Cache-Control", "private, no-cache, no-store, max-age=0, must-revalidate");

        // Redirect to homepage if already logged in
        if ($this->session->has("user_id")) {
            return $this->response->redirect("/");
        }
    }

    public function pickLookAction()
    {
        if ($this->request->isPost()) {
            // TODO: validate figure
            // TODO: use proper Phalcon validators

            $figure = $this->request->getPost('figure', 'string', GameConfiguration::getString('new_user.figure')); // TODO: preg_replace to only allow numeric and '-', '.' characters
            $this->session->set("register-figure", $figure);

            $gender = $this->request->getPost("gender", 'string', GameConfiguration::getString('new_user.gender'));
            $this->session->set("register-gender", $gender);

            return $this->response->redirect('/register/enter-details');
        }

        if ($this->session->has('register-gender')) {
            $this->view->gender = $this->session->get('register-gender');
        } else {
            $this->view->gender = GameConfiguration::getString('new_user.gender');
        }

        if ($this->session->has('register-figure')) {
            $this->view->figure = $this->session->get('register-figure');
        } else {
            $this->view->figure = GameConfiguration::getString('new_user.figure');
        }

        $this->view->setMainView('register/pick-look');
    }

    public function detailsAction()
    {
        if ($this->request->isPost()) {
            // Sanitize input
            // TODO: handle -=?!@:. in username
            $username = $this->filter->sanitize($this->request->getPost('username', 'string'), 'alphanum');
            $password = $this->request->getPost('password', 'striptags');
            $retypedPassword = $this->request->getPost('retypedPassword', 'striptags');

            // Assign sanitized input to view
            $this->view->username = $username;
            $this->view->password = $password;
            $this->view->retypedPassword = $retypedPassword;

            $registrationErrors = [];

            // Validate if username is provided
            // Uses multibyte (UTF-8) string length check
            if (mb_strlen($username) == 0) {
                $registrationErrors[] = 'Please choose your name';
            }

            // Validate if password is provided
            // Uses multibyte (UTF-8) string length check
            if (mb_strlen($password) == 0) {
                $registrationErrors[] = 'Please enter a password';
            }

            // Password should be longer than six characters
            if (mb_strlen($password) < 6) {
                $registrationErrors[] = 'Password is too short';
            }

            // TODO: better similarity algorithm that also handles @, ! etc
            if (similar_text(strtolower('MOD'), mb_substr(strtolower($username), 0, 3)) == 3) {
                $registrationErrors[] = 'The MOD prefix is reserved for staff';
            }

            // Validate if retypedPassword is provided
            // Uses multibyte (UTF-8) string length check
            if (mb_strlen($retypedPassword) == 0) {
                $registrationErrors[] = 'Please type your password again';
            }

            // Check if password and retypedPassword equal
            if ($password != $retypedPassword) {
                $registrationErrors[] = 'The passwords you typed are not identical';
            }

            // Do not continue if there are validation errors
            if (count($registrationErrors) > 0) {
                $this->view->register_errors = $registrationErrors;
                return;
            }

            // Check if username already exists (match case-insensitive)
            $user = \Users::findFirst([
                "LOWER(username) = :username:",
                "bind" => [
                    'username' => mb_strtolower($username)
                ],
                'limit' => 1
            ]);

            // If user already exists, show error, else create user
            if ($user) {
                $this->view->register_errors = ['Sorry, the name you picked is already in use.'];
            } else {
                // Start a transaction
                $this->db->begin();

                $user = new \Users();
                $user->username = $username;
                $user->password = $this->security->hash($password);
                $user->motto = GameConfiguration::getString('new_user.motto');
                $user->credits = GameConfiguration::getInteger('new_user.credits');
                $user->tickets = 0;
                $user->film = 0;
                $user->rank = 1;
                $user->console_motto = GameConfiguration::getString('new_user.console_motto');
                $user->last_online = 0;
                $user->sso_ticket = '';
                $user->pool_figure = '';
                $user->club_subscribed = 0;
                $user->club_expiration = 0;
                $user->allow_stalking = true;
                $user->sound_enabled = true;
                $user->badge = '';
                $user->badge_active = false;

                // If user chose an gender in the previous page, use that, else use default
                if ($this->session->has('register-gender')) {
                    $user->sex = $this->session->get('register-gender');
                } else {
                    $user->sex = GameConfiguration::getString('new_user.gender');
                }

                // If user chose an figure in the previous page, use that, else use default
                if ($this->session->has('register-figure')) {
                    $user->figure = $this->session->get('register-figure');
                } else {
                    $user->figure = GameConfiguration::getString('new_user.figure');
                }

                // Try to create user, if this fails; show error in view
                // TODO: log create error
                if (!$user->create()) {
                    $this->view->register_errors = ['Unable to create user, contact administrator'];
                    // TODO: log $user->getMessages();
                    $this->db->rollback();
                } else {
                    // User has been created!

                    // Now we will give the user badges!
                    $badges = [];
                    $defaultBadges = explode(',', GameConfiguration::getString('new_user.badges'));
                    $i = 0;

                    // Add badges for new user
                    foreach ($defaultBadges as $badge) {
                        $badges[$i] = new UsersBadges();
                        $badges[$i]->user_id = $user->id;
                        $badges[$i]->badge = $badge;
                        $badges[$i]->create();
                        $i++;
                    }

                    // Set active badge
                    $user = Users::findFirstById($user->id);
                    $user->badge = $i > 0 ? $defaultBadges[0] : '';
                    $user->badge_active = intval($i > 0);

                    if (!$user->update()) {
                        // TODO: log error
                    }

                    // Commit the transaction
                    $this->db->commit();

                    // Regenerate session id to protect from session hijacking
                    $this->session->regenerateId(true);
                    $this->session->set("user_id", $user->id);

                    return $this->response->redirect('/');
                }
            }
        }
    }
}
