
document.addEventListener('DOMContentLoaded', function() {

	init();


})

function init() {


    // ACCORDEONS
    const closeDropdownsContent = function( $els ) {
        console.log('closeDropdownsContent');

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
        console.log('handleDropdownsOpening');

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
    
    function get_filters( els ) {

        for (const el of els) {
            el.addEventListener('click', function (e) {
                console.log('please do filter');


                const query_data = new FormData();
                const grid = document.querySelector('#grid');
                const taxonomy = this.getAttribute('data-taxonomy');
                const term = this.getAttribute('data-term');
                const termID = this.getAttribute('data-termID');
                const postType = grid.getAttribute('data-posttype');
                const step = 24;


                els.forEach(element => {
                    element.classList.remove('active');
                });
                if( term == "all" ) {
                    document.querySelectorAll('[data-term="all"]').forEach(el => {
                        el.classList.add('active');
                    });
                }
                else {
                    this.classList.add('active');
                }
                
                query_data.append( 'action', 'loadposts' );
                query_data.append( 'nonce', ajax_var.nonce );
                query_data.append( 'taxonomy', taxonomy);
                query_data.append( 'term', term);
                query_data.append( 'termID', termID);
                query_data.append( 'postType', postType);
                query_data.append( 'offset', 0);


                grid.style.opacity = "0.5";

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
                        document.querySelector('#grid').innerHTML = data;
                        grid.style.opacity = "1";


                        const load_more = document.querySelector('#loadMore');
                        load_more.classList.remove('hidden'); 

                        // const posts_nav = document.querySelector('#posts_nav');
                        // posts_nav.classList.add('hidden');



                        load_more.addEventListener('click', function() {

                            grid.style.opacity = '.5';
    
                            let current_offset = parseInt(query_data.get('offset'));
                            let new_offset = parseInt(current_offset + step)
                            query_data.set('offset', new_offset);
    
                            fetch(ajax_var.ajax_url, {
                                    method: "POST",
                                    credentials: 'same-origin',
                                    body: query_data
                                })
                                .then((response) => {
                                    return response.json()
                                })
                                .then((data) => {
                                    
                                    if( !data || data.length === 1 ) {
                                        load_more.classList.add('hidden'); 
                                    }
                                    else {
                                        grid.insertAdjacentHTML("beforeend", data);
                                        grid.querySelectorAll('.posts_navigation').forEach(el => {
                                            el.remove();
                                        })
                                    }
                                    grid.style.opacity = '1';
                                })
                                .catch((error) => {
                                    console.log(error);
                                });
    
                        });

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
            const end = st.dataset.end;
            studios_dates_array.push(start);
            if( end == '' ) {
                studios_dates_array.push('present');
            }
        }
        console.log(studios_dates_array);

        let timeline_nav = document.createElement('div');
        timeline_nav.classList.add('page_filters');
        let timeline_nav_html = '<ul class="filters_group">';
        
        for (const date of studios_dates_array) {
            timeline_nav_html += `<li class=""><a class="timeline_item" href="#about-${date}">${date}</a></li>`;
        }

        timeline_nav_html += '</ul>';
        timeline_nav.innerHTML = timeline_nav_html;

        document.querySelector('.section-studio').prepend(timeline_nav);
    }


}