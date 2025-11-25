<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $complaint_id
 * @property string $file_path
 * @property string $file_name
 * @property string $file_type
 * @property int $file_size
 * @property string|null $mime_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Complaint $complaint
 * @property-read string $file_url
 * @property-read \App\Models\User|null $uploader
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment query()
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereComplaintId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereFileSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereFileType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereUpdatedAt($value)
 */
	class Attachment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $reference_number
 * @property int $user_id
 * @property int $entity_id
 * @property string $type
 * @property string|null $location
 * @property string $description
 * @property string $status
 * @property int|null $locked_by
 * @property string|null $locked_at
 * @property int|null $assigned_to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $assignedTo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\Entity $entity
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ComplaintHistory> $history
 * @property-read int|null $history_count
 * @property-read \App\Models\User|null $lockedBy
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint query()
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereAssignedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereLockedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Complaint whereUserId($value)
 */
	class Complaint extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $complaint_id
 * @property int $user_id
 * @property string $action
 * @property string|null $description
 * @property array|null $old_data
 * @property array|null $new_data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Complaint $complaint
 * @property-read string $action_description
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory whereComplaintId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory whereNewData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory whereOldData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ComplaintHistory whereUserId($value)
 */
	class ComplaintHistory extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Complaint> $complaints
 * @property-read int|null $complaints_count
 * @method static \Illuminate\Database\Eloquent\Builder|Entity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Entity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Entity query()
 * @method static \Illuminate\Database\Eloquent\Builder|Entity whereContactEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Entity whereContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Entity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Entity whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Entity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Entity whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Entity whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Entity whereUpdatedAt($value)
 */
	class Entity extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property array|null $details
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $timestamp
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|Log newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Log newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Log query()
 * @method static \Illuminate\Database\Eloquent\Builder|Log whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Log whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Log whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Log whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Log whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Log whereTimestamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Log whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Log whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Log whereUserId($value)
 */
	class Log extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property mixed $password
 * @property int|null $entity_id
 * @property int $failed_login_attempts
 * @property string|null $account_locked_until
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ComplaintHistory> $complaintHistories
 * @property-read int|null $complaint_histories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Complaint> $complaints
 * @property-read int|null $complaints_count
 * @property-read \App\Models\Entity|null $entity
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAccountLockedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereFailedLoginAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder|User withoutRole($roles, $guard = null)
 */
	class User extends \Eloquent {}
}

