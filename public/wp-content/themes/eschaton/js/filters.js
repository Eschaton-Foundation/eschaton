
document.addEventListener('DOMContentLoaded', function() {

	init();


})

function init() {


    // ACCORDEONS
    const closeDropdownsContent = function( $els ) {
        //console.log('closeDropdownsContent');

        let i = 0;

        for (let el of $els) {

            var this_height = el.offsetHeight + 500;            
            el.style.maxHeight = this_height + 'px';
            el.closest('.js_dropdown').classList.add('closed');

            // Don't close for first item
            // if( i > 0 ) {
            //     el.closest('.js_dropdown').classList.add('closed');
            // }

            i++;
        }

    }

    const handleDropdownsOpening = function( $els ) {
        //console.log('handleDropdownsOpening');

        for (let el of $els) {

            const js_dropdown = el.closest('.js_dropdown');

            el.addEventListener('click', function(e) {
                e.preventDefault();
    
                if ( ! el.closest('.js_dropdown').classList.contains("closed") ) {
                    el.closest('.js_dropdown').classList.add('closed');
                    return;
                }
                for( let j of $els) {
                    j.closest('.js_dropdown').classList.add('closed');
                }
    
                js_dropdown.classList.toggle('closed')
    
            }); 

        }
    }


    // handle accordéons
    const $js_dropdown = document.querySelectorAll('.js_dropd_link');
    const $js_dropd_content = document.querySelectorAll('.js_dropd_content');

    closeDropdownsContent( $js_dropd_content );
    handleDropdownsOpening( $js_dropdown );


    


    /*
     * Active filter for publications template
     * get an array of elements
    */

    const query_data = new FormData();

    function setQueryDatas( el ) {
        const taxonomy = el.getAttribute('data-taxonomy');
        const term = el.getAttribute('data-term');
        const termID = el.getAttribute('data-termID');
        const postType = grid.getAttribute('data-posttype');
        const step = grid.getAttribute('data-step');

        query_data.set( 'action', 'loadposts' );
        query_data.set( 'nonce', ajax_var.nonce );
        query_data.set( 'taxonomy', taxonomy);
        query_data.set( 'term', term);
        query_data.set( 'termID', termID);
        query_data.set( 'postType', postType);
        query_data.set( 'step', step);
    }


    let loadMore = document.querySelector('#loadMore');
    let grid = document.querySelector('#grid');




    loadMore.addEventListener('click', function() {
        //console.log('loadmore !');


        // FRONT STUFFS

        if( document.querySelector('.exhibitions-grid-past') !== null  ) {
            grid = document.querySelector('.exhibitions-grid-past');
        }
        else {
            grid = document.querySelector('#grid');
        }

        grid.style.opacity = '.5';


        // QUERY DATAS

        if( query_data.get('term') === null ) {
            setQueryDatas( this );
        }
        query_data.set('loadmore', true);

        let current_offset;
        let step = parseInt(query_data.get('step'));

        if( query_data.get('offset') !== null ) {
            current_offset = parseInt(query_data.get('offset'));
        }
        else {
            current_offset = parseInt(0);
        }

        let new_offset = parseInt(current_offset + step )
        query_data.set('offset', new_offset);
    

        // FETCH POSTS
        
        fetch(ajax_var.ajax_url, {
            method: "POST",
            credentials: 'same-origin',
            body: query_data
        })
            .then((response) => {
                return response.json()
            })
            .then((data) => {
                         
                if( !data || data.length < 20 ) {
                    loadMore.classList.add('hidden'); 
                }
                else {
                    grid.insertAdjacentHTML("beforeend", data);
                    // grid.querySelectorAll('.posts_navigation').forEach(el => {
                    //     el.remove();
                    // })
                }
                grid.style.opacity = '1';
            })
            .catch((error) => {
                console.log(error);
            });
    
    });

    

    
    function get_filters( els ) {

        for (const el of els) {
            el.addEventListener('click', function (e) {
                //console.log('please do filter');

                grid = document.querySelector('#grid');


                // GET QUERY DATAS

                setQueryDatas( this );
                query_data.set('offset', 0);
                query_data.set('loadmore', false);

                //console.log(query_data);


                // FRONT STUFFS

                els.forEach(element => {
                    element.classList.remove('active');
                });

                if( query_data.get('term') == "all" ) {
                    document.querySelectorAll('[data-term="all"]').forEach(el => {
                        el.classList.add('active');
                    });
                }
                else {
                    this.classList.add('active');
                }

                grid.style.opacity = "0.5";



                // FETCH DATAS

                fetch(ajax_var.ajax_url, {
                    method: "POST",
                    credentials: 'same-origin',
                    body: query_data
                })
                    .then((response) => {
                        // console.log(response);
                        return response.json()
                    })
                    .then((data) => {
                        // console.log(data);
                        grid.innerHTML = data;
                        grid.style.opacity = "1";
                        loadMore.classList.remove('hidden'); 

                    })
                    .catch((error) => {
                        console.log(error);
                    });

            })
        }
    }

    const filter_buttons = document.querySelectorAll('.filter-item');
    get_filters(filter_buttons);










    // STUDIO

    const studios = document.querySelectorAll('.studio_single');

    if(studios.length > 0 ) {

        let studios_dates_array = [];

        for (const st of studios) {
            const start = st.dataset.start;
            let end = st.dataset.end;
            if( end == '' ) {
                end = 'present';
            }
            studios_dates_array.push([start, end]);
        }
        console.log(studios_dates_array);

        let timeline_nav = document.createElement('div');
        timeline_nav.classList.add('page_filters');
        let timeline_nav_html = '<ul class="">';
        
        for (const date of studios_dates_array) {
            timeline_nav_html += `<li class="filters_group"><a class="timeline_item" href="#about-${date[0]}">${date[0]} - ${date[1]}</a></li>`;
        }

        timeline_nav_html += '</ul>';
        timeline_nav.innerHTML = timeline_nav_html;

        document.querySelector('.section-studio .listing_w_filters').prepend(timeline_nav);
    }


}