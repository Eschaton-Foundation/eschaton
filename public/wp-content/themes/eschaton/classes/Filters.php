<?php

function FILTERS( $label, $taxonomy) {
	return new Filters( $label, $taxonomy);
}  

/*
 * Classe Filters
 * Permet de générer des filtres basés sur une taxonomie WP.
 */
class Filters {

    /*
     * @var string Label du bouton 'all'
     */
    private $_allLabel;

    /*
     * @var string La taxonomy sur laquelle se base le fitre
     */
    private $_taxonomy;


    public function __construct( $label = 'All', $taxonomy ) {
        $this->_allLabel = $label;
        $this->_taxonomy = $taxonomy;
    }

    /*
     * @return String HTML
     */
    public function getOutput() {

        ob_start(); ?>

        <div class="filters_group">
            <button class="filter-item active" data-taxonomy="<?php echo $this->_taxonomy; ?>" data-term="all">
                <?php echo $this->_allLabel; ?>
            </button>

            <?php 
            $types = get_terms( array(
                'taxonomy' => $this->_taxonomy,
                'hide_empty' => true
            ) );
            
            if ( !empty($types) ) :
                foreach( $types as $term ) {

                    $output = '<button class="filter-item" data-taxonomy="' . $this->_taxonomy . '" data-term="' . $term->slug . '" data-termID="' . $term->term_id . '">';
                    $output.= esc_attr( $term->name );
                    $output.='</button>';
                    echo $output;
                }
            endif; ?>
        </div>


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