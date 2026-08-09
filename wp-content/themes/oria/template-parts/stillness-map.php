<?php
/**
 * The Stillness Map canvas — generated from the prototype. The region slugs
 * on each <g> match the area taxonomy's region terms, and app.js fills the
 * counts from window.ORIA_DATA at load.
 */

declare(strict_types=1);
?>
<div class="stillmap" id="stillmap" data-default="central">
      <div class="stillmap__canvas reveal">
        <svg class="stillmap__svg" viewBox="0 0 760 900" role="img" aria-label="Stylised map of the Perth metropolitan area showing eight regions">
          <title>Practices across the Perth metropolitan area</title>
          <defs>
            <linearGradient id="hillband" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0" stop-color="#A9C2B7" stop-opacity="0"/>
              <stop offset="1" stop-color="#A9C2B7" stop-opacity=".13"/>
            </linearGradient>
            <linearGradient id="seaband" x1="1" y1="0" x2="0" y2="0">
              <stop offset="0" stop-color="#C8D9CF" stop-opacity="0"/>
              <stop offset="1" stop-color="#C8D9CF" stop-opacity=".08"/>
            </linearGradient>
          </defs>

          <!-- ocean -->
          <rect x="0" y="0" width="200" height="900" fill="url(#seaband)"/>
          <!-- land -->
          <path d="M150 0 C138 60 132 110 136 140 C142 190 158 215 155 250 C152 285 128 305 130 335 C133 380 154 405 150 435
                   C146 470 118 492 121 522 C124 556 145 580 140 612 C135 646 104 668 107 700 C110 736 132 766 130 800
                   C128 838 144 872 152 900 L760 900 L760 0 Z"
                fill="rgba(255,255,255,.05)" stroke="rgba(255,255,255,.16)" stroke-width="1.5"/>
          <!-- the scarp -->
          <rect x="560" y="0" width="200" height="900" fill="url(#hillband)"/>
          <path d="M600 0 C588 140 604 260 596 380 C588 500 606 620 598 740 C594 800 600 860 604 900"
                fill="none" stroke="rgba(255,255,255,.13)" stroke-width="1.5" stroke-dasharray="3 7"/>
          <!-- Swan and Canning rivers -->
          <path d="M126 600 C190 588 236 572 292 540 C348 508 384 476 414 434 C444 392 452 340 464 280 C474 226 490 180 508 148"
                fill="none" stroke="#A9C2B7" stroke-opacity=".55" stroke-width="3.5" stroke-linecap="round"/>
          <path d="M292 540 C322 566 346 604 366 646 C382 680 392 706 398 728"
                fill="none" stroke="#A9C2B7" stroke-opacity=".35" stroke-width="2.5" stroke-linecap="round"/>

          <text x="34" y="330" class="edit" fill="rgba(200,217,207,.45)" font-size="17" font-family="Newsreader, serif" font-style="italic" transform="rotate(-90 34 330)">Indian Ocean</text>
          <text x="672" y="112" class="edit" fill="rgba(200,217,207,.4)" font-size="15" font-family="Newsreader, serif" font-style="italic" text-anchor="end">Darling Scarp</text>

          <g class="region" data-region="north" tabindex="0" role="button" aria-label="Northern Suburbs" style="--delay:.0s">
            <circle class="region__halo" cx="238" cy="168" r="26"/>
            <circle class="region__dot" cx="238" cy="168" r="5"/>
            <circle class="region__ring" cx="238" cy="168" r="38"/>
            <text class="region__label" x="266" y="164">Northern Suburbs</text>
            <text class="region__count" x="266" y="182">—</text>
            <circle class="region__hit" cx="238" cy="168" r="48"/>
          </g>

          <g class="region" data-region="swan" tabindex="0" role="button" aria-label="Swan Valley and East" style="--delay:1.1s">
            <circle class="region__halo" cx="530" cy="290" r="26"/>
            <circle class="region__dot" cx="530" cy="290" r="5"/>
            <circle class="region__ring" cx="530" cy="290" r="38"/>
            <text class="region__label" x="558" y="286">Swan Valley &amp; East</text>
            <text class="region__count" x="558" y="304">—</text>
            <circle class="region__hit" cx="530" cy="290" r="48"/>
          </g>

          <g class="region" data-region="central" tabindex="0" role="button" aria-label="Perth Central" style="--delay:.4s">
            <circle class="region__halo" cx="352" cy="430" r="30"/>
            <circle class="region__dot" cx="352" cy="430" r="5.5"/>
            <circle class="region__ring" cx="352" cy="430" r="42"/>
            <text class="region__label" x="382" y="426">Perth Central</text>
            <text class="region__count" x="382" y="444">—</text>
            <circle class="region__hit" cx="352" cy="430" r="52"/>
          </g>

          <g class="region" data-region="west" tabindex="0" role="button" aria-label="Western Suburbs" style="--delay:2.2s">
            <circle class="region__halo" cx="196" cy="470" r="24"/>
            <circle class="region__dot" cx="196" cy="470" r="5"/>
            <circle class="region__ring" cx="196" cy="470" r="36"/>
            <text class="region__label" x="224" y="466">Western Suburbs</text>
            <text class="region__count" x="224" y="484">—</text>
            <circle class="region__hit" cx="196" cy="470" r="46"/>
          </g>

          <g class="region" data-region="hills" tabindex="0" role="button" aria-label="Perth Hills" style="--delay:1.7s">
            <circle class="region__halo" cx="632" cy="486" r="24"/>
            <circle class="region__dot" cx="632" cy="486" r="5"/>
            <circle class="region__ring" cx="632" cy="486" r="36"/>
            <text class="region__label" x="604" y="482" text-anchor="end">Perth Hills</text>
            <text class="region__count" x="604" y="500" text-anchor="end">—</text>
            <circle class="region__hit" cx="632" cy="486" r="46"/>
          </g>

          <g class="region" data-region="freo" tabindex="0" role="button" aria-label="Fremantle and South" style="--delay:.8s">
            <circle class="region__halo" cx="192" cy="618" r="26"/>
            <circle class="region__dot" cx="192" cy="618" r="5"/>
            <circle class="region__ring" cx="192" cy="618" r="38"/>
            <text class="region__label" x="220" y="614">Fremantle &amp; South</text>
            <text class="region__count" x="220" y="632">—</text>
            <circle class="region__hit" cx="192" cy="618" r="48"/>
          </g>

          <g class="region" data-region="southeast" tabindex="0" role="button" aria-label="South East" style="--delay:2.8s">
            <circle class="region__halo" cx="466" cy="672" r="24"/>
            <circle class="region__dot" cx="466" cy="672" r="5"/>
            <circle class="region__ring" cx="466" cy="672" r="36"/>
            <text class="region__label" x="494" y="668">South East</text>
            <text class="region__count" x="494" y="686">—</text>
            <circle class="region__hit" cx="466" cy="672" r="46"/>
          </g>

          <g class="region" data-region="peel" tabindex="0" role="button" aria-label="Rockingham and Peel" style="--delay:1.4s">
            <circle class="region__halo" cx="204" cy="812" r="24"/>
            <circle class="region__dot" cx="204" cy="812" r="5"/>
            <circle class="region__ring" cx="204" cy="812" r="36"/>
            <text class="region__label" x="232" y="808">Rockingham &amp; Peel</text>
            <text class="region__count" x="232" y="826">—</text>
            <circle class="region__hit" cx="204" cy="812" r="46"/>
          </g>
        </svg>
      </div>

        <div class="stillmap__panel reveal" id="mapPanel" aria-live="polite"></div>
</div>
