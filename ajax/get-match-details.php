<?php
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    die('Match ID required');
}

$conn = getDatabaseConnection();
$match_id = $_GET['id'];

$sql = "SELECT m.*, s.full_name as scout_name, s.email as scout_email
        FROM matches m 
        LEFT JOIN scouts s ON m.assigned_scout_id = s.id 
        WHERE m.id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$match_id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    echo '<div class="message error">Match not found</div>';
    exit;
}

$status_classes = [
    'scheduled' => 'status-scheduled',
    'ongoing' => 'status-ongoing',
    'completed' => 'status-completed',
    'cancelled' => 'status-cancelled'
];
?>

<div class="match-details-content">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <div>
            <h4 style="color: var(--gray); font-size: 14px; margin-bottom: 8px;">MATCH</h4>
            <div style="font-size: 24px; font-weight: 700; color: var(--dark);">
                <?php echo htmlspecialchars($match['home_team']); ?> vs <?php echo htmlspecialchars($match['away_team']); ?>
            </div>
        </div>
        
        <div>
            <h4 style="color: var(--gray); font-size: 14px; margin-bottom: 8px;">STATUS</h4>
            <span class="status-badge <?php echo $status_classes[$match['status']]; ?>" style="font-size: 14px;">
                <?php echo ucfirst($match['status']); ?>
            </span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <div>
            <h4 style="color: var(--gray); font-size: 14px; margin-bottom: 8px;">DATE & TIME</h4>
            <p style="color: var(--dark); font-weight: 500;">
                <?php echo date('F j, Y', strtotime($match['match_date'])); ?>
                <?php if ($match['match_time']): ?>
                <br>
                <small style="color: var(--gray);"><?php echo date('g:i A', strtotime($match['match_time'])); ?></small>
                <?php endif; ?>
            </p>
        </div>
        
        <div>
            <h4 style="color: var(--gray); font-size: 14px; margin-bottom: 8px;">VENUE</h4>
            <p style="color: var(--dark); font-weight: 500;">
                <?php echo htmlspecialchars($match['venue']); ?>
                <?php if ($match['country']): ?>
                <br>
                <small style="color: var(--gray);"><?php echo htmlspecialchars($match['country']); ?></small>
                <?php endif; ?>
            </p>
        </div>
        
        <div>
            <h4 style="color: var(--gray); font-size: 14px; margin-bottom: 8px;">COMPETITION</h4>
            <p style="color: var(--dark); font-weight: 500;">
                <?php echo $match['competition'] ? htmlspecialchars($match['competition']) : 'Not specified'; ?>
            </p>
        </div>
        
        <div>
            <h4 style="color: var(--gray); font-size: 14px; margin-bottom: 8px;">ASSIGNED SCOUT</h4>
            <p style="color: var(--dark); font-weight: 500;">
                <?php if ($match['scout_name']): ?>
                <?php echo htmlspecialchars($match['scout_name']); ?>
                <?php if ($match['scout_email']): ?>
                <br>
                <small style="color: var(--gray);"><?php echo htmlspecialchars($match['scout_email']); ?></small>
                <?php endif; ?>
                <?php else: ?>
                <span style="color: var(--gray);">Not assigned</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if ($match['notes']): ?>
    <div style="margin-top: 24px;">
        <h4 style="color: var(--gray); font-size: 14px; margin-bottom: 8px;">NOTES</h4>
        <div style="background: #f8fafc; padding: 16px; border-radius: 8px; border-left: 4px solid var(--primary);">
            <?php echo nl2br(htmlspecialchars($match['notes'])); ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div style="margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end;">
    <form method="POST" action="../admin-matches.php" style="display: inline;">
        <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
        <input type="hidden" name="status" value="completed">
        <button type="submit" name="update_status" class="btn btn-primary">
            <i class="fas fa-check"></i> Mark as Completed
        </button>
    </form>
    
    <button onclick="editMatch(<?php echo $match['id']; ?>)" class="btn btn-secondary">
        <i class="fas fa-edit"></i> Edit Match
    </button>
</div>