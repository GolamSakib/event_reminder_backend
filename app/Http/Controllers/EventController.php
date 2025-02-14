<?php
namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'completed'    => 'sometimes|boolean', // New validation rule for completed status
        ]);

        $event = Event::updateOrCreate(
            ['event_reminder_id_from_browser' => $id,
             'created_by'=> Auth::user()->id,
            ],
            [
                'event_reminder_id' => 'EVT-' . $validated['id'],
                'name'              => $validated['name'],
                'created_by'        => Auth::id(),
                'startDate'         => $validated['startDate'],
                'endDate'           => $validated['endDate'],
                'completed'         => $validated['completed'] ?? false, // Set completed status
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
        'status'  => 200
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
}
