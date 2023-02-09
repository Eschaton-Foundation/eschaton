<?php

function FILTERS( $label = "All", $taxonomy, $onlyParent = false) {
	return new Filters( $label, $taxonomy, $onlyParent);
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

    /*
     * @var int 
     */
    private $_onlyParent;


    public function __construct( $label, $taxonomy, $onlyParent ) {
        $this->_allLabel = $label;
        $this->_taxonomy = $taxonomy;
        $this->_onlyParent = $onlyParent;
    }

    
    /*
     * @return String HTML
     */
    public function getOutput() {

        ob_start(); 

        $args = array(
            'taxonomy'      => $this->_taxonomy,
            'hide_empty'    => true,
        );

        if( $this->_onlyParent ) {
            $args['parent'] = 0;
        }

        $types = get_terms( $args );
            
        if ( !empty($types) ) : ?>

            <div class="filters_group">

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


    /*
     * @return un echo de l'ouptu String HTML
     */
    public function displayOutput() {
        echo $this->getOutput();
    }
    

}