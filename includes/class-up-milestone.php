<?php

namespace UpStream;

// Prevent direct access.

use UpStream\Traits\PostMetadata;

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * @since   1.24.0
 */
class Milestone extends Struct
{
    use PostMetadata;

    /**
     * @var \WP_Post
     */
    protected $post;

    /**
     * @var int
     */
    protected $projectId;

    /**
     * @var array
     */
    protected $assignedTo;

    /**
     * Start date in MySQL timestamp.
     *
     * @var string
     */
    protected $startDate;

    /**
     * End date in MySQL timestamp.
     *
     * @var string
     */
    protected $endDate;

    /**
     * @var string
     */
    protected $notes;

    /**
     * @var int
     */
    protected $order;

    /**
     * @var int
     */
    protected $createdBy;

    /**
     * Created on date, in MySQL format
     *
     * @var string
     */
    protected $createdOn;

    /**
     * @var float
     */
    protected $progress;

    /**
     * @var string
     */
    protected $color;

    /**
     * @var string
     */
    protected $legacyId;

    /**
     * @var string
     */
    protected $legacyMilestoneCode;

    /**
     * @deprecated only for storing the legacy value from the old architecture.
     *
     * @var bool
     */
    protected $createdTimeInUtc;

    /**
     * @var int
     */
    protected $taskCount;

    /**
     * @var int
     */
    protected $taskOpen;

    /**
     * The Post Type for milestones.
     */
    const POST_TYPE = 'upst_milestone';

    /**
     * Project ID meta key.
     */
    const META_PROJECT_ID = 'upst_project_id';

    /**
     * Assigned To meta key.
     */
    const META_ASSIGNED_TO = 'upst_assigned_to';

    /**
     * Start date meta key.
     */
    const META_START_DATE = 'upst_start_date';

    /**
     * End date meta key.
     */
    const META_END_DATE = 'upst_end_date';

    /**
     * Order meta key.
     */
    const META_ORDER = 'upst_order';

    /**
     * Progress meta key.
     */
    const META_PROGRESS = 'upst_progress';

    /**
     * Color meta key.
     */
    const META_COLOR = 'upst_color';

    /**
     * Legacy ID meta key.
     */
    const META_LEGACY_ID = 'upst_legacy_id';

    /**
     * Legacy code used to link tasks to milestones.
     */
    const META_LEGACY_MILESTONE_CODE = 'upst_legacy_milestone_code';

    /**
     * Legacy flag for when the created time was set in UTC.
     */
    const META_CREATED_TIME_IN_UTC = 'upst_created_time_in_utc';

    /**
     * Task count meta key.
     */
    const META_TASK_COUNT = 'upst_task_count';

    /**
     * Task open meta key.
     */
    const META_TASK_OPEN = 'upst_task_open';

    /**
     * Milestone constructor.
     *
     * @param int|\WP_Post|string $post
     *
     * @throws \Exception
     */
    public function __construct($post)
    {
        if (empty($post)) {
            throw new Exception('Invalid milestone post ID');
        }

        if (is_object($post)) {
            if ($post->post_type !== self::POST_TYPE) {
                throw new Exception(__('Invalid Post Type for the given post.', 'upstream'));
            }

            $this->postId = $post->ID;
            $this->post   = $post;
        }

        if (is_numeric($post)) {
            $this->postId = $post;
            $this->post   = $this->getPost();
        }

        // Are we filtering by the legacy ID?
        if (is_string($post) && ! is_numeric($post)) {
            $this->post = $this->getPost($post);

            if (empty($this->post)) {
                throw new Exception('Milestone not found');
            }

            $this->postId = $this->post->ID;
        }
    }

    /**
     * @param string|null
     *
     * @return \WP_Post|false
     *
     * @throws Exception
     */
    public function getPost($legacyId = null)
    {
        if (empty($this->post)) {
            if (empty($this->postId) && empty($legacyId)) {
                return false;
            }

            if (empty($legacyId)) {
                $this->post = get_post($this->postId);
            } else {
                $posts = get_posts([
                    'post_type'  => Milestone::POST_TYPE,
                    'status'     => 'publish',
                    'meta_key'   => self::META_LEGACY_ID,
                    'meta_value' => sanitize_text_field($legacyId),
                ]);

                if ( ! empty($posts)) {
                    $this->post = $posts[0];
                } else {
                    throw new Exception('Milestone not found');
                }
            }
        }

        return $this->post;
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getName()
    {
        return $this->getPost()->post_title;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->postId;
    }

    /**
     * @return int
     */
    public function getProjectId()
    {
        if (null === $this->projectId) {
            $this->projectId = $this->getMetadata(self::META_PROJECT_ID, true);
        }

        return $this->projectId;
    }

    /**
     * @param int $projectId
     *
     * @return Milestone
     */
    public function setProjectId($projectId)
    {
        $this->projectId = (int)$projectId;

        $this->updateMetadata([self::META_PROJECT_ID => $projectId]);

        return $this;
    }

    /**
     * @return array
     */
    public function getAssignedTo()
    {
        if (null === $this->assignedTo) {
            $this->assignedTo = $this->getMetadata(self::META_ASSIGNED_TO, false);
        }

        return (array)$this->assignedTo;
    }

    /**
     * @param array $array
     *
     * @return array $array
     */
    protected function removeEmptyValuesFromArray($array)
    {
        if ( ! empty($array)) {
            $array = array_unique($array);
            $array = array_filter($array);
        }

        return $array;
    }

    /**
     * @param array $array
     *
     * @return array array
     */
    protected function sanitizeArrayOfIds($array)
    {
        if ( ! empty($array)) {
            $array = array_map('intval', $array);

            $array = $this->removeEmptyValuesFromArray($array);
        }

        return $array;
    }

    /**
     * @param array $assignedTo
     *
     * @return Milestone
     */
    public function setAssignedTo($assignedTo)
    {
        if ( ! is_array($assignedTo)) {
            $assignedTo = [];
        }

        $this->assignedTo = $this->sanitizeArrayOfIds($assignedTo);

        $this->updateNonUniqueMetadata(self::META_ASSIGNED_TO, $this->assignedTo);

        return $this;
    }

    /**
     * @param string $format mysql, unix, upstream
     *
     * @return string
     */
    public function getStartDate($format = 'mysql')
    {
        if (null === $this->startDate) {
            $this->startDate = $this->getMetadata(self::META_START_DATE, true);
        }

        return $this->getDateOnFormat($this->startDate, $format);
    }

    /**
     * @param int|string $startDate
     *
     * @return Milestone
     */
    public function setStartDate($startDate)
    {
        $startDate = $this->getMySQLDate($startDate);

        $this->startDate = $startDate;

        // Assume it is on MySQL date format.
        $this->updateMetadata([self::META_START_DATE => $startDate]);

        return $this;
    }

    /**
     * @param mixed $date
     *
     * @return false|mixed|string
     */
    protected function getMySQLDate($date)
    {
        if ( ! $this->dateIsMySQLDateFormat($date)) {
            if ( ! $this->dateIsUnixTime($date)) {
                // Convert to unix time.
                $date = upstream_date_unixtime($date);
            }

            // Assume it is in unix time format and convert to MySQL date format.
            if ( ! empty($date)) {
                $date = date('Y-m-d', $date);
            }
        }

        return $date;
    }

    /**
     * @param int|string $date
     *
     * @return bool
     */
    protected function dateIsMySQLDateFormat($date)
    {
        return preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $date);
    }

    /**
     * @param $date
     *
     * @return bool
     */
    protected function dateIsUnixTime($date)
    {
        return preg_match('/^\d+$/', $date);
    }

    /**
     * @param $date
     * @param $format
     *
     * @return false|int|mixed
     */
    protected function getDateOnFormat($date, $format)
    {
        if ($format === 'unix') {
            return strtotime($date);
        }

        if ($format === 'upstream') {
            if ( ! preg_match('/^\d+$/', $date)) {
                $date = strtotime($date);
            }

            return upstream_format_date($date);
        }

        return $date;
    }

    /**
     * @param string $format mysql, unix, upstream
     *
     * @return string
     */
    public function getEndDate($format = 'mysql')
    {
        if (null === $this->endDate) {
            $this->endDate = $this->getMetadata(self::META_END_DATE, true);
        }

        return $this->getDateOnFormat($this->endDate, $format);
    }

    /**
     * @param int|string $endDate
     *
     * @return Milestone
     */
    public function setEndDate($endDate)
    {
        $endDate = $this->getMySQLDate($endDate);

        $this->endDate = $endDate;

        // Assume it is on MySQL date format.
        $this->updateMetadata([self::META_END_DATE => $endDate]);

        return $this;
    }

    /**
     * @return string|null
     *
     * @throws Exception
     */
    public function getNotes()
    {
        if ( ! empty($this->notes)) {
            return $this->notes;
        }

        $this->notes = $this->getPost()->post_content;

        return $this->notes;
    }

    /**
     * @return \UpStream\Milestones
     */
    protected function getMilestonesInstance()
    {
        return Milestones::getInstance();
    }

    /**
     * @param string $notes
     *
     * @return Milestone
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;

        $milestones = $this->getMilestonesInstance();

        remove_action('save_post', [$milestones, 'savePost']);
        wp_update_post(
            [
                'ID'           => $this->postId,
                'post_content' => $notes,
            ]
        );
        add_action('save_post', [$milestones, 'savePost']);

        return $this;
    }

    /**
     * @param string $notes
     *
     * @return Milestone
     */
    public function setName($newName)
    {
        $this->post->post_title = $newName;

        $milestones = $this->getMilestonesInstance();

        remove_action('save_post', [$milestones, 'savePost']);
        wp_update_post(
            [
                'ID'         => $this->postId,
                'post_title' => $newName,
            ]
        );
        add_action('save_post', [$milestones, 'savePost']);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getOrder()
    {
        if ( ! empty($this->order)) {
            return $this->order;
        }

        $this->order = $this->getMetadata(self::META_ORDER, true);

        return (int)$this->order;
    }

    /**
     * @param int|string $order
     *
     * @return Milestone
     */
    public function setOrder($order)
    {
        $order = (int)$order;

        $this->$order = $order;

        // Assume it is on MySQL date format.
        $this->updateMetadata([self::META_ORDER => $order]);

        return $this;
    }

    /**
     * @return int|null
     *
     * @throws Exception
     */
    public function getCreatedBy()
    {
        if ( ! empty($this->createdBy)) {
            return $this->createdBy;
        }

        $this->createdBy = (int)$this->getPost()->post_author;

        return $this->createdBy;
    }

    /**
     * @param string $format
     *
     * @return int|null
     */
    public function getCreatedOn($format = 'mysql')
    {
        if ( ! empty($this->createdOn)) {
            return $this->createdOn;
        }

        $this->createdOn = get_the_date('Y-m-d', $this->postId);

        return $this->getDateOnFormat($this->createdOn, $format);
    }

    /**
     * @return float|int
     */
    public function getProgress()
    {
        if ( ! empty($this->progress)) {
            return $this->progress;
        }

        $this->progress = $this->getMetadata(self::META_PROGRESS, true);

        if (empty($this->progress)) {
            $this->progress = 0.00;
        }

        return (float)$this->progress;
    }

    /**
     * @return string|null
     */
    public function getColor()
    {
        if ( ! empty($this->color)) {
            return $this->color;
        }

        $this->color = $this->getMetadata(self::META_COLOR, true);

        return $this->color;
    }

    /**
     * @param string $newColor
     *
     * @return Milestone
     */
    public function setColor($newColor)
    {
        $this->color = sanitize_text_field($newColor);

        $this->updateMetadata([self::META_COLOR => $newColor]);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLegacyId()
    {
        if ( ! empty($this->legacyId)) {
            return $this->legacyId;
        }

        $this->legacyId = $this->getMetadata(self::META_LEGACY_ID, true);

        return $this->legacyId;
    }

    /**
     * @param string $newLegacyId
     *
     * @return Milestone
     */
    public function setLegacyId($newLegacyId)
    {
        $this->legacyId = sanitize_text_field($newLegacyId);

        $this->updateMetadata([self::META_LEGACY_ID => $newLegacyId]);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLegacyMilestoneCode()
    {
        if ( ! empty($this->legacyMilestoneCode)) {
            return $this->legacyMilestoneCode;
        }

        $this->legacyMilestoneCode = $this->getMetadata(self::META_LEGACY_MILESTONE_CODE, true);

        return $this->legacyMilestoneCode;
    }

    /**
     * @param string $newLegacyMilestoneCode
     *
     * @return Milestone
     */
    public function setLegacyMilestoneCode($newLegacyMilestoneCode)
    {
        $this->legacyMilestoneCode = sanitize_text_field($newLegacyMilestoneCode);

        $this->updateMetadata([self::META_LEGACY_MILESTONE_CODE => $newLegacyMilestoneCode]);

        return $this;
    }

    /**
     * @return bool|null
     */
    public function getCreatedTimeInUtc()
    {
        if ( ! empty($this->createdTimeInUtc)) {
            return $this->createdTimeInUtc;
        }

        $this->createdTimeInUtc = $this->getMetadata(self::META_CREATED_TIME_IN_UTC, true);

        return $this->createdTimeInUtc;
    }

    /**
     * @param bool $newCreatedTimeInUtc
     *
     * @return Milestone
     */
    public function setCreatedTimeInUtc($newCreatedTimeInUtc)
    {
        $this->createdTimeInUtc = sanitize_text_field($newCreatedTimeInUtc);

        $this->updateMetadata([self::META_CREATED_TIME_IN_UTC => $this->createdTimeInUtc]);

        return $this;
    }

    /**
     * @return int|null
     */
    public function getTaskCount()
    {
        if ( ! empty($this->taskCount)) {
            return $this->taskCount;
        }

        $this->taskCount = $this->getMetadata(self::META_TASK_COUNT, true);

        return $this->taskCount;
    }

    /**
     * @param int $newTaskCount
     *
     * @return Milestone
     */
    public function setTaskCount($newTaskCount)
    {
        $this->taskCount = sanitize_text_field($newTaskCount);

        $this->updateMetadata([self::META_TASK_COUNT => $newTaskCount]);

        return $this;
    }

    /**
     * @return int|null
     */
    public function getTaskOpen()
    {
        if ( ! empty($this->taskOpen)) {
            return $this->taskOpen;
        }

        $this->taskOpen = $this->getMetadata(self::META_TASK_OPEN, true);

        return $this->taskOpen;
    }

    /**
     * @param int $newTaskOpen
     *
     * @return Milestone
     */
    public function setTaskOpen($newTaskOpen)
    {
        $this->taskOpen = sanitize_text_field($newTaskOpen);

        $this->updateMetadata([self::META_TASK_OPEN => $newTaskOpen]);

        return $this;
    }

    /**
     * @param float $newProgress
     *
     * @return Milestone
     */
    public function setProgress($newProgress)
    {
        $this->progress = (float)$newProgress;

        $this->updateMetadata([self::META_PROGRESS => $newProgress]);

        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function convertToLegacyRowset()
    {
        $assignees = $this->getAssignedTo();

        $row = [
            'id'              => $this->getId(),
            'milestone'       => $this->getName(),
            'milestone_order' => $this->getOrder(),
            'created_by'      => $this->getCreatedBy(),
            'created_time'    => $this->getCreatedOn('unix'),
            'assigned_to'     => $assignees,
            'progress'        => $this->getProgress(),
            'notes'           => $this->getNotes(),
            'start_date'      => $this->getStartDate('unix'),
            'end_date'        => $this->getEndDate('unix'),
            'task_count'      => $this->getTaskCount(),
            'task_open'       => $this->getTaskOpen(),
        ];

        if ( ! empty($assignees)) {
            // Get the name of assignees to fix ordering.
            $row['assigned_to_order'] = upstream_get_users_display_name($assignees);
        }

        return $row;
    }

    /**
     * Delete the milestone.
     */
    public function delete()
    {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            $projectId = $this->getProjectId();


            // TODO: Refator this after migrating tasks to another architecture.
            $tasks = (array)get_post_meta($projectId, '_upstream_project_tasks', true);
            if (count($tasks) > 0) {
                $updated = false;

                foreach ($tasks as &$task) {
                    if (isset($task['milestone']) && $task['milestone'] === $this->getId()) {
                        $task['milestone'] = '';

                        $updated = true;
                    }
                }

                if ($updated) {
                    update_post_meta($projectId, '_upstream_project_tasks', $tasks);
                }
            }

            wp_trash_post($this->getId());

            $activity = Factory::getActivity();
            $activity->add_activity($projectId, '_upstream_project_milestones', 'remove',
                $this->convertToLegacyRowset());

            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
        }
    }
}
