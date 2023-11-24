<?php

if ( ! function_exists( 'get_event_dates' ) ) :

	function get_event_dates( $id ) { 

        $start_date = get_field("date_start", $id);
        $start_date_timestamp = strtotime( $start_date );
        $start_year = date('Y', $start_date_timestamp);
        $start_date_noyear = date('j.m', $start_date_timestamp);

        $end_date = get_field("date_end", $id);
        $start_end_timestamp = strtotime( $end_date );
        $end_year = date('Y', $start_end_timestamp);

        if( has_term( 'permanent', 'exhpermanent' )  || has_term( 'permanent-fr', 'exhpermanent' ) || has_term( 'permanent-de', 'exhpermanent')  ) {
            if( pll_current_language( ) === "en" ) {
                $end_date = 'Ongoing';
            }
            else if (pll_current_language( ) === "fr") {
                $end_date = 'Installation permanente';
            }
            else if (pll_current_language( ) === "de") {
                $end_date = 'Permanent';
            }
        }

        if( $start_year === $end_year ) {
            echo $start_date_noyear . ' - ' . $end_date;
        }
        else {
            echo $start_date . ' - ' . $end_date;
        }
    }
endif;
