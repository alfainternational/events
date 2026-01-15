<?php
/**
 * Event Controller
 * يدير جميع عمليات الفعاليات
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/EventModel.php';
require_once __DIR__ . '/../Services/EventService.php';

class EventController extends BaseController {
    private $eventModel;
    private $eventService;
    
    public function __construct($pdo) {
        parent::__construct($pdo);
        $this->eventModel = new EventModel($pdo);
        $this->eventService = new EventService($pdo);
    }
    
    /**
     * عرض جميع الفعاليات
     */
    public function index() {
        $status = $this->input('status', 'all');
        
        if ($status === 'all') {
            $events = $this->eventModel->allActive();
        } else {
            $events = $this->eventModel->getByStatus($status);
        }
        
        return $this->view('events/index', [
            'events' => $events,
            'status' => $status
        ]);
    }
    
    /**
     * عرض فعالية واحدة
     */
    public function show($id) {
        $event = $this->eventModel->getWithDetails($id);
        
        if (!$event) {
            $this->redirect('/activity/', 'الفعالية غير موجودة', 'error');
        }
        
        return $this->view('events/show', ['event' => $event]);
    }
    
    /**
     * إنشاء فعالية جديدة
     */
    public function create() {
        $this->validateCsrf();
        
        // Rate limiting
        require_once __DIR__ . '/../../includes/rate_limiter.php';
        $rateLimiter = new RateLimiter($this->pdo);
        if (!$rateLimiter->checkLimit('booking', 3, 60)) {
            return $this->json(['error' => 'لقد تجاوزت الحد المسموح، حاول لاحقاً'], 429);
        }
        
        // Validation
        $validation = $this->validate($_POST, [
            'title' => 'required|min:5|max:255',
            'organizing_dept' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'location_type' => 'required'
        ]);
        
        if ($validation !== true) {
            return $this->json(['errors' => $validation], 422);
        }
        
        try {
            $eventId = $this->eventService->createEvent($_POST);
            
            $this->logAction('create', $eventId, null, $_POST);
            
            return $this->json([
                'success' => true,
                'event_id' => $eventId,
                'message' => 'تم إنشاء الطلب بنجاح'
            ]);
            
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * تحديث فعالية
     */
    public function update($id) {
        $this->validateCsrf();
        
        $oldEvent = $this->eventModel->find($id);
        
        if (!$oldEvent) {
            return $this->json(['error' => 'الفعالية غير موجودة'], 404);
        }
        
        // Validation
        $validation = $this->validate($_POST, [
            'title' => 'required|min:5|max:255',
            'organizing_dept' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date'
        ]);
        
        if ($validation !== true) {
            return $this->json(['errors' => $validation], 422);
        }
        
        try {
            $success = $this->eventService->updateEvent($id, $_POST);
            
            if ($success) {
                $this->logAction('update', $id, $oldEvent, $_POST);
                
                return $this->json([
                    'success' => true,
                    'message' => 'تم تحديث الفعالية بنجاح'
                ]);
            }
            
            return $this->json(['error' => 'فشل التحديث'], 500);
            
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * الموافقة على فعالية
     */
    public function approve($id) {
        $this->requirePermission('approve_events');
        $this->validateCsrf();
        
        $event = $this->eventModel->find($id);
        
        if (!$event) {
            return $this->json(['error' => 'الفعالية غير موجودة'], 404);
        }
        
        try {
            $this->eventService->approveEvent($id);
            
            $this->logAction('approve', $id, 
                ['status' => $event['status']], 
                ['status' => 'approved']
            );
            
            return $this->json([
                'success' => true,
                'message' => 'تم قبول الطلب بنجاح'
            ]);
            
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * رفض فعالية
     */
    public function reject($id) {
        $this->requirePermission('reject_events');
        $this->validateCsrf();
        
        $event = $this->eventModel->find($id);
        
        if (!$event) {
            return $this->json(['error' => 'الفعالية غير موجودة'], 404);
        }
        
        try {
            $reason = $this->input('reason', 'لم يتم ذكر السبب');
            $this->eventService->rejectEvent($id, $reason);
            
            $this->logAction('reject', $id, 
                ['status' => $event['status']], 
                ['status' => 'rejected', 'reason' => $reason]
            );
            
            return $this->json([
                'success' => true,
                'message' => 'تم رفض الطلب'
            ]);
            
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * حذف فعالية (soft delete)
     */
    public function delete($id) {
        $this->requirePermission('delete_events');
        $this->validateCsrf();
        
        try {
            $this->eventModel->softDelete($id);
            
            $this->logAction('delete', $id);
            
            return $this->json([
                'success' => true,
                'message' => 'تم نقل الطلب إلى سلة المحذوفات'
            ]);
            
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * البحث
     */
    public function search() {
        $criteria = [
            'q' => $this->input('q'),
            'status' => $this->input('status'),
            'location_type' => $this->input('location_type'),
            'hall_id' => $this->input('hall_id'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'sort' => $this->input('sort')
        ];
        
        $results = $this->eventModel->search($criteria);
        
        return $this->json([
            'success' => true,
            'results' => $results,
            'count' => count($results)
        ]);
    }
}
?>
