<?php

function FILTERS( $label = "All", $taxonomy, $display = 'column', $onlyParent = false) {
	return new Filters( $label, $taxonomy, $display, $onlyParent);
}  

function FILTERS_ACF( $label = "All", $taxonomy, $display = 'column', $onlyParent = false) {
	return new FiltersACF( $label, $taxonomy, $display, $onlyParent);
}  

/*
 * Classe Filters
 * Permet de générer des filtres basés sur une taxonomie WP.
 */
class Filters {

    /*
     * @var string Label du bouton 'all'
     */
    protected $_allLabel;

    /*
     * @var string La taxonomy sur laquelle se base le fitre
     */
    protected $_taxonomy;

    /*
     * @var int 
     */
    protected $_onlyParent;

        /*
     * @var int 
     */
    protected $_display;



    public function __construct( $label, $taxonomy, $display, $onlyParent ) {
        $this->_allLabel = $label;
        $this->_taxonomy = $taxonomy;
        $this->_display = $display;
        $this->_onlyParent = $onlyParent;
    }

    
    /*
     * @return String HTML
     */
    public function getOutput() {

        ob_start(); 

        if( $this->_taxonomy !== '' ) : 
            $args = array(
                'taxonomy'      => $this->_taxonomy,
                'hide_empty'    => true,
            );

            if( $this->_onlyParent || $this->_display === 'tree' ) {
                $args['parent'] = 0;
            }

            $types = get_terms( $args );
                
            if ( !empty($types) ) : ?>

                <div class="filters_group <?php echo $this->_display; ?>">

                    <?php if( $this->_allLabel !== '' ) : ?>
                        <button class="filter-item active" data-taxonomy="<?php echo $this->_taxonomy; ?>" data-term="all">
                            <?php echo $this->_allLabel; ?>
                        </button>
                    <?php endif; ?>

                    <?php foreach( $types as $term ) {

                        if ( $this->_display === 'tree' && $this->_onlyParent ) {

                            $output = '<div class="terms-group js_dropdown">';

                                $output .= '<p class="filter-parent js_dropd_link">' . $term->name . '<i class="fas fa-sharp fa-solid fa-caret-right"></i></p>';

                                //$term_children = get_term_children( $term->term_id, $this->_taxonomy );
                                $term_children = get_terms( $this->_taxonomy, array( 'child_of' => $term->term_id ) );

                                $output .= '<ul class="children-group inline js_dropd_content">';

                                    foreach ( $term_children as $child ) {

                                        //$child_term_obj = get_term_by( 'id', $child, $this->_taxonomy );
                                        $output .= '<li><button class="filter-item" data-taxonomy="' . $this->_taxonomy . '" data-term="' . $child->slug . '" data-termID="' . $child->term_idd . '">';
                                        $output .= esc_attr( $child->name );
                                        $output .='</button></li>';
                                    }
                                
                                $output .= '</ul>';

                            $output .= '</div>';
                        }
                        else {
                            $output = '<button class="filter-item" data-taxonomy="' . $this->_taxonomy . '" data-term="' . $term->slug . '" data-termID="' . $term->term_id . '">';
                            $output.= esc_attr( $term->name );
                            $output.='</button>';
                        }

                        echo $output;

                    } ?>

                </div>
                
            <?php endif; ?>

        <?php else: ?>

            <div class="filters_group <?php echo $this->_display; ?>">
                <button class="filter-item active" data-taxonomy="" data-term="all">
                    <?php echo $this->_allLabel; ?>
                </button>
            </div>

        <?php endif; ?>


        <?php 
        $finalString = ob_get_contents();
        ob_end_clean();
        return $finalString;
    }


    /*
     * @return un echo de l'ouptu String HTML
     */
    public function displayOutput() {
        echo $this->getOutput();
    }
    

}



class FiltersACF extends Filters {


    public function __construct( $label, $taxonomy, $display, $onlyParent ) {
        $this->_allLabel = $label;
        $this->_taxonomy = $taxonomy;
        $this->_display = $display;
        $this->_onlyParent = $onlyParent;
    }

    /*
     * @return String HTML
     */
    public function getOutput() {

        ob_start(); 
            
        if ( !empty($types) ) : ?>

            <div class="filters_group <?php echo $this->_display; ?>">

                <button class="filter-item active" data-taxonomy="<?php echo $this->_taxonomy; ?>" data-term="all">
                    <?php echo $this->_allLabel; ?>
                </button>

                <?php foreach( $types as $term ) {
                    $output = '<button class="filter-item" data-taxonomy="' . $this->_taxonomy . '" data-term="' . $term->slug . '" data-termID="' . $term->term_id . '">';
                    $output.= esc_attr( $term->name );
                    $output.='</button>';
                    echo $output;
                } ?>

            </div>


        <?php endif; ?>


        <?php 
        $finalString = ob_get_contents();
        ob_end_clean();
        return $finalString;
    }
}