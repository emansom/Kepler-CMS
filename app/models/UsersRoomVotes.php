<?php

class UsersRoomVotes extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Column(column="user_id", type="integer", length=11, nullable=false)
     */
    public $user_id;

    /**
     *
     * @var integer
     * @Column(column="room_id", type="integer", length=11, nullable=false)
     */
    public $room_id;

    /**
     *
     * @var integer
     * @Column(column="vote", type="integer", length=11, nullable=false)
     */
    public $vote;


    public function initialize()
    {

        $this->setSource("users_room_votes");
    }


    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }


    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'users_room_votes';
    }

}
