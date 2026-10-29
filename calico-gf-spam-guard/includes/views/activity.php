<?php
defined( 'ABSPATH' ) || exit;
$activity = $this->activity();
$total = count( $activity );
$pages = max( 1, (int) ceil( $total / 20 ) );
$number = min( $pages, max( 1, absint( $_GET['log_page'] ?? 1 ) ) );
$visible = array_slice( $activity, ( $number - 1 ) * 20, 20 );
?>
<h2>Activity Log</h2>
<p>Recent rule matches, newest first. Review the original submissions in Gravity Forms’ Spam entries. This log does not copy submitted messages, emails or IPs.</p>
<p><?php echo esc_html( $total ? 'Showing ' . ( ( $number - 1 ) * 20 + 1 ) . '–' . min( $number * 20, $total ) . ' of ' . $total . ' events' : 'No recent matches.' ); ?></p>
<table class="widefat striped"><thead><tr><th>Time (site timezone)</th><th>Form / entry</th><th>Result</th><th>Matched rules</th><th>Quarantine alert</th></tr></thead><tbody>
<?php foreach ( $visible as $row ) :
    $link = admin_url( 'admin.php?page=gf_entries&id=' . (int) $row['form'] . '&filter=spam' );
    if ( ! empty( $row['entry_id'] ) ) { $link = admin_url( 'admin.php?page=gf_entries&view=entry&id=' . (int) $row['form'] . '&lid=' . (int) $row['entry_id'] ); }
?>
<tr class="cgsg-event"><td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $row['time'] ) ); ?></td><td><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( 'Form ' . $row['form'] . ( ! empty( $row['entry_id'] ) ? ' / #' . $row['entry_id'] : '' ) ); ?></a></td><td><?php echo esc_html( $row['action'] ); ?></td><td><?php echo esc_html( $row['labels'] ); ?></td><td><?php echo esc_html( $row['alert'] ?? '—' ); ?></td></tr>
<?php endforeach; if ( ! $visible ) { echo '<tr><td colspan="5">No recent matches.</td></tr>'; } ?>
</tbody></table>
<?php if ( $pages > 1 ) : ?>
<nav aria-label="Activity log pages" class="tablenav"><div class="tablenav-pages">
<?php for ( $i = 1; $i <= $pages; $i++ ) {
    if ( $i === $number ) { echo '<span class="button disabled" aria-current="page">' . (int) $i . '</span> '; }
    else { echo '<a class="button" href="' . esc_url( add_query_arg( 'log_page', $i, $this->url( 'activity' ) ) ) . '">' . (int) $i . '</a> '; }
} ?>
</div></nav>
<?php endif; $this->start_form( 'clear' ); submit_button( 'Clear activity log', 'secondary' ); ?></form>
