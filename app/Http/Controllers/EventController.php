<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with('participants')
            ->orderBy('event_reminder_id_from_browser')
            ->get();

        $formattedEvents = $events->map(function ($event) {
            return [
                'id'                             => $event->event_reminder_id_from_browser,
                'event_reminder_id_from_browser' => $event->event_reminder_id_from_browser,
                'event_reminder_id'              => $event->event_reminder_id,
                'name'                           => $event->name,
                'description'                    => $event->description,
                'created_by'                     => $event->created_by,
                'startDate'                      => $event->startDate,
                'endDate'                        => $event->endDate,
                'completed'                      => $event->completed,
                'created_at'                     => $event->created_at,
                'updated_at'                     => $event->updated_at,
                'is_notification_sent'           => $event->is_notification_sent,
                'participants'                   => $event->participants->pluck('participant_email')->toArray(),
            ];
        });

        return response()->json([
            'data'   => $formattedEvents,
            'status' => 200,
        ]);
    }
    public function store(Request $request)
    {
        try {
            $request->validate([
                'id'           => 'required|numeric',
                'name'         => 'required|string|max:255',
                'startDate'    => 'required|date',
                'endDate'      => 'required|date',
                'participants' => 'sometimes|array',
            ]);
            $email = $request->participants;

            $event = Event::create([
                'event_reminder_id_from_browser' => $request->id,
                'event_reminder_id'              => 'EVT-' . $request->id,
                'name'                           => $request->name,
                'created_by'                     => Auth::user()->id,
                'startDate'                      => $request->startDate,
                'endDate'                        => $request->endDate,
            ]);

            if ($request->has('participants')) {
                foreach ($request->participants as $participant) {
                    $event->participants()->create([
                        'participant_email' => $participant,
                        'created_by'        => Auth::user()->id,
                    ]);
                }
            }
            return response()->json([
                'message' => 'Event created successfully',
                'status'  => 200,
            ]);

            return response()->json($event, 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

    }

    public function show($id)
    {
        $event = Event::with('participants')->findOrFail($id);
        return response()->json($event);
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'id'           => 'required|numeric',
                'name'         => 'required|string|max:255',
                'startDate'    => 'required|date',
                'endDate'      => 'required|date',
                'participants' => 'sometimes|array',
                'completed'    => 'sometimes|boolean',
            ]);

            $event = Event::updateOrCreate(
                ['event_reminder_id_from_browser' => $id,
                    'created_by'                      => Auth::user()->id,
                ],
                [
                    'event_reminder_id' => 'EVT-' . $validated['id'],
                    'name'              => $validated['name'],
                    'created_by'        => Auth::id(),
                    'startDate'         => $validated['startDate'],
                    'endDate'           => $validated['endDate'],
                    'completed'         => $validated['completed'] ?? false,
                ]
            );

            if ($request->has('participants')) {
                $event->participants()->delete();
                foreach ($request->participants as $participant) {
                    $event->participants()->create([
                        'participant_email' => $participant,
                        'created_by'        => Auth::user()->id,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Event updated successfully',
                'status'  => 200,
                'data'    => $event,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $event = Event::where('event_reminder_id_from_browser', $id)->where('created_by', Auth::user()->id)->with('participants')->first();
        $event->participants()->delete();
        $event->delete();
        return response()->json([
            'message' => 'Event deleted successfully',
            'status'  => 200,
        ]);

    }

    public function addParticipant(Request $request, $eventId)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email',
        ]);

        $event       = Event::findOrFail($eventId);
        $participant = $event->participants()->create($request->only(['name', 'email']));
        return response()->json($participant, 201);
    }

public function import(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:csv,xlsx,xls,txt'
    ]);

    try {
        $file = $request->file('file');
        $path = $file->getRealPath();

        // Parse CSV file
        $handle = fopen($path, 'r');

        // Read headers
        $headers = fgetcsv($handle);

        // Validate headers
        $requiredHeaders = ['name', 'startDate', 'endDate', 'participants'];
        $missingHeaders = array_diff($requiredHeaders, $headers);

        if (!empty($missingHeaders)) {
            return response()->json([
                'message' => 'Missing required columns: ' . implode(', ', $missingHeaders)
            ], 422);
        }

        // Map headers to column indexes
        $headerMap = array_flip($headers);

        $importedCount = 0;
        $errors = [];
        $row = 2; // Start from row 2 (after headers)
        $participantsToCreate = []; // Store participant data

        DB::beginTransaction();

        try {
            while (($data = fgetcsv($handle)) !== false) {
                $event_reminder_id_from_browser = (int) (microtime(true) * 10000);


                $rawStartDate = $data[$headerMap['startDate']] ?? '';
                $rawEndDate = $data[$headerMap['endDate']] ?? '';

                // Convert and validate dates using Carbon
                try {
                    $startDate = Carbon::parse($rawStartDate)->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $errors[] = "Row {$row}: Invalid startDate format '{$rawStartDate}'";
                    continue;
                }

                try {
                    $endDate = Carbon::parse($rawEndDate)->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $errors[] = "Row {$row}: Invalid endDate format '{$rawEndDate}'";
                    continue;
                }

                // Ensure endDate is not before startDate
                if (Carbon::parse($endDate)->lessThan(Carbon::parse($startDate))) {
                    $errors[] = "Row {$row}: endDate '{$endDate}' was before startDate '{$startDate}', swapping dates.";
                    [$startDate, $endDate] = [$endDate, $startDate]; // Swap dates
                }

                $eventData = [
                    'name' => $data[$headerMap['name']] ?? '',
                    'event_reminder_id_from_browser' => $event_reminder_id_from_browser,
                    'event_reminder_id' => "EVT-" . $event_reminder_id_from_browser,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'created_by' => auth()->id()
                ];

                // Validate event data
                $validator = Validator::make($eventData, [
                    'name' => 'required|string|max:255',
                    'startDate' => 'required|date',
                    'endDate' => 'required|date|after_or_equal:startDate',
                ]);

                if ($validator->fails()) {
                    throw new \Exception('Validation failed: ' . implode(', ', $validator->errors()->all()));
                }

                // Create event
                $event = Event::create($eventData);

                // Process participants
                $participantsStr = $data[$headerMap['participants']] ?? '';
                $participantEmails = array_map('trim', explode(',', $participantsStr));

                foreach ($participantEmails as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $participantsToCreate[] = [
                            'event_id' => $event->id,
                            'participant_email' => $email,
                            'created_by' => auth()->id(),
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    } else if (!empty($email)) {
                        $errors[] = "Row {$row}: Invalid email format '{$email}'";
                    }
                }

                $importedCount++;
                $row++;
            }

            // Batch insert participants
            if (!empty($participantsToCreate)) {
                foreach (array_chunk($participantsToCreate, 1000) as $chunk) {
                    Participant::insert($chunk);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import completed successfully',
                'imported_count' => $importedCount,
                'participants_count' => count($participantsToCreate),
                'warnings' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed',
                'errors' => ["Row {$row}: " . $e->getMessage()]
            ], 422);
        }

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Import failed: ' . $e->getMessage()
        ], 500);
    } finally {
        if (isset($handle)) {
            fclose($handle);
        }
    }
}

}
