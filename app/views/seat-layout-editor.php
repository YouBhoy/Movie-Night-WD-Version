<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seat Layout Editor - Admin Panel</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/seat-layout.css">

</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chair"></i> Seat Layout Editor</h1>
            <div class="header-actions">
                <a href="admin-dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo sanitizeInput($message); ?>
            </div>
        <?php endif; ?>

        <div class="controls-section">
            <h2 style="color: #ffd700; margin-bottom: 1.5rem;">
                <i class="fas fa-cog"></i> Layout Controls
            </h2>

            <div class="form-row">
                <div class="form-group" style="position: relative; display: flex; align-items: center;">
                    <label class="form-label">Cinema Hall</label>
                    <select id="hallSelector" class="form-select">
                        <option value="">Select a hall</option>
                        <?php foreach (
                            $halls as $hall): ?>
                            <option value="<?php echo $hall['id']; ?>">
                                <?php echo sanitizeInput($hall['hall_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="position: relative; display: flex; align-items: center;">
                    <label class="form-label">Shift</label>
                    <select id="shiftSelector" class="form-select">
                        <option value="">Select a shift</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Add New Seat</label>
                    <div class="add-seat-controls">
                        <input type="text" id="newRowLetter" class="form-input" placeholder="Row (A-Z)" maxlength="1">
                        <input type="number" id="newSeatPosition" class="form-input" placeholder="Position" min="1">
                        <select id="newSeatStatus" class="form-select">
                            <option value="available">Available</option>
                        </select>
                        <button type="button" id="addSeatBtn" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="seat-grid-container">
            <div class="seat-grid-header">
                <div class="seat-grid-title">
                    <i class="fas fa-th"></i>
                    <span id="gridTitle">Select a hall and shift to view seats</span>
                </div>
                <div class="seat-grid-actions">
                    <button type="button" id="saveLayoutBtn" class="btn btn-primary" disabled>
                        <i class="fas fa-save"></i> Save Layout
                    </button>
                    <button type="button" id="resetBtn" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>

            <div class="screen-indicator">
                <i class="fas fa-tv"></i> SCREEN
            </div>

            <div id="seatGrid" class="seat-grid">
                <div style="text-align: center; color: #94a3b8; padding: 3rem;">
                    <i class="fas fa-chair" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>Select a hall and shift to start editing the seat layout</p>
                </div>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #10b981;"></div>
                    <span>Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ef4444;"></div>
                    <span>Occupied</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: rgba(255,255,255,0.1); border: 2px dashed rgba(255,255,255,0.3);"></div>
                    <span>Empty (Click to add)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Hall Management Section -->
    <div class="controls-section" id="hallManagementSection" style="display: none;">
      <h3 class="text-warning mb-3"><i class="fas fa-building"></i> Cinema Hall Management</h3>

      <!-- Hall List Section -->
      <div id="hallListSection">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="text-warning mb-0">Active Cinema Halls</h6>
          <div>
            <button type="button" class="btn btn-primary btn-sm" onclick="showAddHallForm()">
              <i class="fas fa-plus"></i> Add New Hall
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="hideHallManagement()">
              <i class="fas fa-times"></i> Close
            </button>
          </div>
        </div>
        <div id="hallList" class="mb-3">
          <!-- Halls will be loaded here -->
        </div>
      </div>

      <!-- Add/Edit Hall Form -->
      <div id="hallFormSection" style="display: none;">
        <h6 class="text-warning mb-3" id="hallFormTitle">Add New Cinema Hall</h6>
        <form id="hallForm">
          <input type="hidden" id="hallId" name="hall_id">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group mb-3">
                <label for="hallName" class="form-label">Hall Name</label>
                <input type="text" class="form-control" id="hallName" name="hall_name" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group mb-3">
                <label for="maxAttendees" class="form-label">Max Attendees per Booking</label>
                <input type="number" class="form-control" id="maxAttendees" name="max_attendees_per_booking" min="1" value="3" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group mb-3">
                <label for="totalSeats" class="form-label">Total Seats</label>
                                                <input type="number" class="form-control" id="totalSeats" name="total_seats" min="1" value="<?php echo $settings['default_seat_count'] ?? 72; ?>" required>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Save Hall
            </button>
            <button type="button" class="btn btn-secondary" onclick="showHallList()">
              <i class="fas fa-arrow-left"></i> Back to List
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Shift Management Section -->
    <div class="controls-section" id="shiftManagementSection" style="display: none;">
      <h3 class="text-warning mb-3"><i class="fas fa-clock"></i> Shift Management</h3>

      <!-- Hall Selection for Shifts -->
      <div class="form-group mb-3">
        <label for="shiftHallSelector" class="form-label">Select Cinema Hall</label>
        <select class="form-select" id="shiftHallSelector">
          <option value="">Choose a hall...</option>
        </select>
      </div>

      <!-- Shift List Section -->
      <div id="shiftListSection">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="text-warning mb-0">Active Shifts</h6>
          <div>
            <button type="button" class="btn btn-primary btn-sm" id="addShiftBtn" onclick="showAddShiftForm()" disabled>
              <i class="fas fa-plus"></i> Add New Shift
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="hideShiftManagement()">
              <i class="fas fa-times"></i> Close
            </button>
          </div>
        </div>
        <div id="shiftList" class="mb-3">
          <!-- Shifts will be loaded here -->
        </div>
      </div>

      <!-- Add/Edit Shift Form -->
      <div id="shiftFormSection" style="display: none;">
        <h6 class="text-warning mb-3" id="shiftFormTitle">Add New Shift</h6>
        <form id="shiftForm">
          <input type="hidden" id="shiftId" name="shift_id">
          <input type="hidden" id="shiftHallId" name="hall_id">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group mb-3">
                <label for="shiftName" class="form-label">Shift Name</label>
                <input type="text" class="form-control" id="shiftName" name="shift_name" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group mb-3">
                <label for="shiftCode" class="form-label">Shift Code</label>
                <input type="text" class="form-control" id="shiftCode" name="shift_code" required>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group mb-3">
                <label for="seatPrefix" class="form-label">Seat Prefix</label>
                <input type="text" class="form-control" id="seatPrefix" name="seat_prefix" maxlength="5">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group mb-3">
                <label for="seatCount" class="form-label">Seat Count</label>
                                                <input type="number" class="form-control" id="seatCount" name="seat_count" min="1" value="<?php echo $settings['default_seat_count'] ?? 72; ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group mb-3">
                <label for="startTime" class="form-label">Start Time</label>
                <input type="time" class="form-control" id="startTime" name="start_time" required>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group mb-3">
                <label for="endTime" class="form-label">End Time</label>
                <input type="time" class="form-control" id="endTime" name="end_time" required>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Save Shift
            </button>
            <button type="button" class="btn btn-secondary" onclick="showShiftList()">
              <i class="fas fa-arrow-left"></i> Back to List
            </button>
          </div>
        </form>
      </div>
    </div>

    <script>window.MovieNightPage = <?= json_encode(['csrfToken' => $csrfToken, 'halls' => $halls, 'shifts' => $shifts, 'defaultSeatCount' => (int)($settings['default_seat_count'] ?? 72)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="assets/js/seat-layout-editor.js"></script>

</body>
</html>