<?php

namespace App\Services\Core;

use App\Constant\ClassTypeScheduleConstant;
use App\Constant\ScheduleTypeConstant;
use App\Helpers\GenericData;
use App\Models\Core\PtBooking;
use App\Models\Account\PtPackage;
use App\Repositories\Account\ClassScheduleSessionRepository;
use App\Repositories\Core\PtBookingRepository;
use App\Services\Account\ClassScheduleService;
use App\Repositories\Account\PtPackageRepository;
use App\Repositories\Core\CustomerPtPackageRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PtBookingService
{
    public function __construct(
        private PtBookingRepository $ptBookingRepository,
        private ClassScheduleService $classScheduleService,
        private PtPackageRepository $ptPackageRepository,
        private CustomerPtPackageRepository $customerPtPackageRepository,
        private ClassScheduleSessionRepository $classScheduleSessionRepository,
    ) {
    }


    /**
     * @param GenericData $genericData
     *
     * @return PtBooking
     */
    public function createPtBooking(GenericData $genericData): PtBooking
    {
       try {
        return DB::transaction(function () use ($genericData) {

            $data = $genericData->getData();

            // Get customer PT package to extract pt_package_id and coach_id
            $customerPtPackage = $this->customerPtPackageRepository->findCustomerPtPackageById(
                $data->customerPtPackageId,
                $genericData->userData->account_id
            );

            // Set ptPackageId from customer PT package
            $genericData->getData()->ptPackageId = $customerPtPackage->pt_package_id;
            $genericData->syncDataArray();

            // find pt package by id
            $ptPackage = $this->ptPackageRepository->findPtPackageById(
                $customerPtPackage->pt_package_id,
                $genericData->userData->account_id
            );

            // create pt booking record
            $ptBooking = $this->ptBookingRepository->createPtBooking($genericData);

            $genericData = $this->transformDataToClassSchedule($genericData, $ptPackage);
            $genericData->syncDataArray();
            // create a class schedule and generate session with pt as class type
            $classSchedule = $this->classScheduleService->createClassSchedule($genericData);

            // update pt booking with class schedule id
            $this->ptBookingRepository->updatePtBookingWithClassScheduleId($genericData->userData->account_id, $ptBooking->id, $classSchedule->id);

            return $ptBooking->fresh();
        });

       } catch (\Throwable $th) {
            Log::error('Failed to create PT booking: ' . $th->getMessage());
            throw $th;
       }
    }

    /**
     * @param int $id
     * @param GenericData $genericData
     *
     * @return PtBooking
     */
    public function updatePtBooking(int $id, GenericData $genericData): PtBooking
    {
        try {
            return DB::transaction(function () use ($id, $genericData) {
                $data = $genericData->getData();

                // Get customer PT package to extract pt_package_id and coach_id
                $customerPtPackage = $this->customerPtPackageRepository->findCustomerPtPackageById(
                    $data->customerPtPackageId,
                    $genericData->userData->account_id
                );

                // Set ptPackageId from customer PT package
                $genericData->getData()->ptPackageId = $customerPtPackage->pt_package_id;
                $genericData->syncDataArray();

                // find pt package by id
                $ptPackage = $this->ptPackageRepository->findPtPackageById(
                    $customerPtPackage->pt_package_id,
                    $genericData->userData->account_id
                );

                // update pt booking record
                $ptBooking = $this->ptBookingRepository->updatePtBooking($id, $genericData);
                $genericData = $this->transformDataToClassSchedule($genericData, $ptPackage);
                // update class schedule and class session
                $classScheduleId = $ptBooking->class_schedule_id;
                if ($classScheduleId) {
                    $this->classScheduleService->updateClassSchedule($classScheduleId, $genericData);
                }

                return $ptBooking->fresh();
            });
        } catch (\Throwable $th) {
            Log::error('Failed to update PT booking: ' . $th->getMessage());
            throw $th;
        }
    }

    /**
     * @param int $id
     * @param GenericData $genericData
     *
     * @return PtBooking
     */
    public function markAsAttended(int $id, GenericData $genericData): PtBooking
    {
        try {
            return DB::transaction(function () use ($id, $genericData) {
                $ptBooking = $this->ptBookingRepository->markAsAttended($id, $genericData);

                // Find the customer PT package by customer_id and pt_package_id
                $customerPtPackage = $this->customerPtPackageRepository->getPtPackages(
                    $ptBooking->customer_id,
                    $genericData
                )->firstWhere('pt_package_id', $ptBooking->pt_package_id);

                // if pt booking update the customer pt package number of sessions
                if ($customerPtPackage) {
                    $this->customerPtPackageRepository->updateCustomerPtPackageSessions(
                        $ptBooking->customer_id,
                        $customerPtPackage->id,
                        $genericData
                    );
                }
                // update attendance count on related class schedule session
                $this->classScheduleSessionRepository->updateAttendanceByClassScheduleId($ptBooking->class_schedule_id, $genericData->userData->account_id);
                $ptBooking->refresh();
                return $ptBooking;
            });
        } catch (\Throwable $th) {
            Log::error('Failed to mark PT booking as attended: ' . $th->getMessage());
            throw $th;
        }
    }




    private function transformDataToClassSchedule(GenericData $genericData, PtPackage $ptPackage): GenericData
    {
        $data = $genericData->getData();
        $dateAndTime = Carbon::parse($data->bookingDate . ' ' . $data->bookingTime);
        // this session is for the coach schedule
        $genericData->getData()->className = $ptPackage->package_name;
        $genericData->getData()->description = $ptPackage->description;
        $genericData->getData()->coachId = $data->coachId;
        $genericData->getData()->capacity = 1;
        $genericData->getData()->duration = $data->duration;
        $genericData->getData()->startDate = $dateAndTime;
        $genericData->getData()->scheduleType = ScheduleTypeConstant::ONE_TIME;
        $genericData->getData()->classType = ClassTypeScheduleConstant::PERSONAL_TRAINING;
        $genericData->getData()->recurringInterval = null;
        $genericData->getData()->numberOfSessions = 1;

        return $genericData;
    }
}
