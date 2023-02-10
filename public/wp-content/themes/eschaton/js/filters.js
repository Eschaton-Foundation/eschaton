
document.addEventListener('DOMContentLoaded', function() {

	init();


})

function init() {


    /*
     * Active filter for publications template
     * get an array of elements
    */
    
    function get_filters( els ) {

        for (const el of els) {
            el.addEventListener('click', function (e) {
                console.log('please do filter');

                els.forEach(element => {
                    element.classList.remove('active');
                });
                this.classList.add('active');

                const data = new FormData();
                const grid = document.querySelector('#grid');
                const taxonomy = this.getAttribute('data-taxonomy');
                const term = this.getAttribute('data-term');
                const termID = this.getAttribute('data-termID');
                const postType = grid.getAttribute('data-posttype');
                console.log(term);

                data.append( 'action', 'loadposts' );
                data.append( 'nonce', ajax_var.nonce );
                data.append( 'taxonomy', taxonomy);
                data.append( 'term', term);
                data.append( 'termID', termID);
                data.append( 'postType', postType);

                // console.log(data);

                grid.style.opacity = "0.5";

                fetch(ajax_var.ajax_url, {
                    method: "POST",
                    credentials: 'same-origin',
                    body: data
                    })
                    .then((response) => {
                        // console.log(response);
                        return response.json()
                    })
                    .then((data) => {
                        // console.log(data);
                        document.querySelector('#grid').innerHTML = data;
                        grid.style.opacity = "1";
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
    timeline_nav_html = '<ul class="filters_group">';
    
    for (const date of studios_dates_array) {
        timeline_nav_html += `<li class=""><a class="timeline_item" href="#about-${date}">${date}</a></li>`;
    }

    timeline_nav_html += '</ul>';
    timeline_nav.innerHTML = timeline_nav_html;

    document.querySelector('.section-studio').prepend(timeline_nav);


}