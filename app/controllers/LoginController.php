<?php

class LoginController extends ControllerBase
{

    public function indexAction()
    {

    }

    public function loginAction() {
        $sessions = $this->getDI()->getShared("session");

        // Redirect to homepage if already logged in
        if ($sessions->has("user_id")) {
            return $this->response->redirect("/");
        }

        if ($this->request->isPost()) {
            $username = $this->request->getPost("username");
            $password = $this->request->getPost("password");

            if ($username === "") {
                $this->flashSession->error("return enter your username");
                //pick up the same view to display the flash session errors
                return $this->view->pick("login");
            }

            if ($password === "") {
                $this->flashSession->error("return enter your password");
                //pick up the same view to display the flash session errors
                return $this->view->pick("login");
            }

            $user = Users::findFirst([
                "conditions" => "username = ?0",
                "bind" == [
                    0 => $username
                ]
            ]);

            if ($user) {
                if ($this->security->checkHash($password, $user->password)) {
                    // Check if we need a rehash
                    if ($this->security->needsRehash($user->password)) {
                        $user->password = $this->security->hash($password);
                        $user->save();
                    }

                    // Clear password from memory securely
                    $this->security->memZeroPassword($password);

                    $sessions->set("user_id", $user->id);
                    return $this->response->redirect("/");
                }
            } else {
                // To protect against timing attacks. Regardless of whether a user exists or not, the script will take roughly the same amount as it will always be computing a hash.
                $this->security->hash(rand());

                $this->flashSession->error("wrong username / password");
            }
        }

    }
}
