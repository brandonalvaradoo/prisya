<?php

function RequireProduct(int $id = 0)
{
    return
    '
    <article class="productcard">
        <div class="cardvisual">

        </div>
        <div class="cardcontent">
            <h2 class="producttitle">Producto</h2>
            <p class="productdescription" lang="es">Lorem ipsum dolor sit amet consectetur adipisicing elit. Temporibus quod fuga dolorum harum id nam! Cum officiis nobis non voluptatibus unde, facere dignissimos esse perspiciatis delectus quod impedit odio accusantium doloribus veritatis repudiandae adipisci ut officia, repellendus temporibus voluptate reprehenderit! Voluptatum veniam asperiores similique tenetur illum molestias quo minus sapiente quidem cum, enim labore ad, ipsam atque autem ullam vitae ex. Quibusdam unde vero eaque itaque, voluptas nostrum hic illo. Quae non delectus repellendus aspernatur deleniti veritatis inventore aut hic nobis, illum, deserunt consequuntur modi possimus. Praesentium illum officia culpa placeat necessitatibus architecto? Laborum voluptatibus incidunt consequuntur molestiae deserunt non.</p>
        </div>
        <div class="cardaction">
            <span class="price">$0.00</span>
            <a href="/" class="view">Ver</a>
        </div>
    </article>
    ';
}