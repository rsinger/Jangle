---
title: Jangle: A generic library API based on the Atom Publishing Protocol
layout: base
---

<div class="row">
  <div class="span12">
    <h3>Introduction and Background to Jangle</h3>
    <p><abbr title="Just Another Next Generation Library Environment">Jangle</abbr> is a specification for applying the <a href="/web/20100911180054/http://bitworking.org/projects/atom/rfc5023.html">Atom Publishing Protocol</a> (AtomPub) to library resources and for exposing these resources simply and RESTfully.</p>
    <p>There are three basic principles that define Jangle:</p>
    <ul>
      <li>The library information model is broken up into four discrete concepts or <strong>entities</strong>: <strong>Actors</strong>, <strong>Resources</strong>, <strong>Items</strong> and <strong>Collections</strong>.</li>
      <li>The Jangle architecture is divided into two components, the <strong>Jangle core</strong>: the public facing AtomPub interface; and one or many <strong>connectors</strong>:  applications that contain the business logic for translating specific systems into Jangle.</li>
      <li>The Jangle core and connectors communicate via an HTTP REST API using a defined JSON syntax.</li>
    </ul>
  </div>
</div>