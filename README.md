Introduction and Background to Jangle

Jangle is a specification for applying the Atom Publishing Protocol (AtomPub) to library resources and for exposing these resources simply and RESTfully.

There are three basic principles that define Jangle:

* The library information model is broken up into four discrete concepts or entities: Actors, Resources, Items and Collections.
* The Jangle architecture is divided into two components, the Jangle core: the public facing AtomPub interface; and one or many connectors: applications that contain the business logic for translating specific systems into Jangle.
* The Jangle core and connectors communicate via an HTTP REST API using a defined JSON syntax.

For search (which is optional), Jangle employs OpenSearch and the Atom Syndication Format for search results and CQL to provide specificity and a common vocabulary of indexes among implementations.

The metadata formats that are exposed at the various entities are defined by the communities of practice that are implementing Jangle, so Integrated Library Management Systems may or may not expose the same metadata formats as an Electronic Resources Management System or Interlibrary Loan System. Alternate metadata formats for feeds or individual resources are advertised via atom:link elements using "rel" attributes comprised of Jangle-specific URIs.

For an application to be "Jangle-compliant", it is not necessary to provide both connector and core components. Compatibility with either component is sufficient to the specification. For example, an application may only provide the connector functionality (a RESTful JSON interface that conforms to the Jangle connector API) or the developer may choose to bypass the core/connector design and apply the AtomPub interface directly to a business logic layer, as long is its behavior resembles that of the Jangle AtomPub specification.