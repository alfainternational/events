<?php
/**
 * Event Service
 * Business logic للفعاليات
 */

require_once __DIR__ . '/../Models/EventModel.php';

class EventService {
    private $pdo;
    private $eventModel;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->eventModel = new EventModel($pdo);
    }
    
    /**
     * إنشاء فعالية جديدة
     */
    public function createEvent($data) {
        // التحقق من تعارض المواعيد
        if ($data['location_type'] === 'internal' && !empty($data['hall_id'])) {
            $conflict = $this->eventModel->checkConflict(
                $data['start_date'],
                $data['end_date'],
                $data['start_time'],
                $data['end_time'],
                $data['hall_id']
            );
            
            if ($conflict) {
                throw new Exception('القاعة محجوزة في هذا الموعد');
            }
        }
        
        // إنشاء edit token
        $data['edit_token'] = bin2hex(random_bytes(16));
        $data['status'] = 'pending';
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $this->eventModel->beginTransaction();
        
        try {
            $eventId = $this->eventModel->create($data);
            
            // حفظ أول نسخة
            require_once __DIR__ . '/../../includes/versioning.php';
            save_event_version($eventId, 'create', 'إنشاء طلب جديد');
            
            $this->eventModel->commit();
            
            return $eventId;
            
        } catch (Exception $e) {
            $this->eventModel->rollback();
            throw $e;
        }
    }
    
    /**
     * تحديث فعالية
     */
    public function updateEvent($id, $data) {
        // التحقق من تعارض المواعيد
        if ($data['location_type'] === 'internal' && !empty($data['hall_id'])) {
            $conflict = $this->eventModel->checkConflict(
                $data['start_date'],
                $data['end_date'],
                $data['start_time'],
                $data['end_time'],
                $data['hall_id'],
                $id // exclude current event
            );
            
            if ($conflict) {
                throw new Exception('القاعة محجوزة في هذا الموعد');
            }
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['status'] = 'pending'; // إعادة للمراجعة
        
        $this->eventModel->beginTransaction();
        
        try {
            // حفظ نسخة قبل التحديث
            require_once __DIR__ . '/../../includes/versioning.php';
            save_event_version($id, 'update', 'تعديل الطلب');
            
            $success = $this->eventModel->update($id, $data);
            
            $this->eventModel->commit();
            
            return $success;
            
        } catch (Exception $e) {
            $this->eventModel->rollback();
            throw $e;
        }
    }
    
    /**
     * الموافقة على فعالية
     */
    public function approveEvent($id) {
        $event = $this->eventModel->find($id);
        
        if (!$event) {
            throw new Exception('الفعالية غير موجودة');
        }
        
        $this->eventModel->beginTransaction();
        
        try {
            // حفظ نسخة
            require_once __DIR__ . '/../../includes/versioning.php';
            save_event_version($id, 'approve', 'تم قبول الطلب');
            
            // تحديث الحالة
            $this->eventModel->updateStatus($id, 'approved');
            
            // إرسال بريد
            if (!empty($event['requester_email'])) {
                require_once __DIR__ . '/../../includes/mailer.php';
                send_email_template('event_approved', 
                    $event['requester_email'],
                    $event['organizing_dept'],
                    [
                        'title' => $event['title'],
                        'organizing_dept' => $event['organizing_dept'],
                        'start_date' => $event['start_date']
                    ]
                );
            }
            
            $this->eventModel->commit();
            
            return true;
            
        } catch (Exception $e) {
            $this->eventModel->rollback();
            throw $e;
        }
    }
    
    /**
     * رفض فعالية
     */
    public function rejectEvent($id, $reason = '') {
        $event = $this->eventModel->find($id);
        
        if (!$event) {
            throw new Exception('الفعالية غير موجودة');
        }
        
        $this->eventModel->beginTransaction();
        
        try {
            // حفظ نسخة
            require_once __DIR__ . '/../../includes/versioning.php';
            save_event_version($id, 'reject', "تم رفض الطلب: {$reason}");
            
            // تحديث الحالة
            $this->eventModel->updateStatus($id, 'rejected');
            
            // إرسال بريد
            if (!empty($event['requester_email'])) {
                require_once __DIR__ . '/../../includes/mailer.php';
                send_email_template('event_rejected', 
                    $event['requester_email'],
                    $event['organizing_dept'],
                    [
                        'title' => $event['title'],
                        'organizing_dept' => $event['organizing_dept'],
                        'reason' => $reason
                    ]
                );
            }
            
            $this->eventModel->commit();
            
            return true;
            
        } catch (Exception $e) {
            $this->eventModel->rollback();
            throw $e;
        }
    }
}
?>
