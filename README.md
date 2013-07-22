Cows-RESTful-API
================

A RESTful api used to interact with the UC Davis COmmunity Web Scheduler

This is intended to replace Cows-TV-Server, Cows-Tablet-Server and Cows-Mobile-Server with a single, more unified API.

Services Provided:
  On failure, all routes return a json object including "code", the error code, and "message", the error message.
  All Returns notes are only in reference to successful executions.

  GET /
  
      Paramaters: format
      Returns: API documentation
      Authentication?: No
      Description: Outputs API documentation in the specified format (currently json or plain (by default)).
  
  GET /error
  
      Paramaters: None
      Returns: Error documentation
      Authentication?: No
      Description: Returns a mapping of error codes to the error type in json format.
  
  POST /session/:siteId
  
      Parameters: tgc - Ticket Granting Cookie from CAS (required)
      Returns: SessionKey
      Authentication?: No
      Description: Used to generate a key to authenticate to any cows service that requires it.
  DELETE /session/:sessionKey
  
      Required Parameters: none
      Returns: Nothing
      Description: Deactivates a session key, and logs you out from cows, and from the cas session represented by
        the tgc. There is no real way to verify if a cas logout was successful, so it still might be in any client's
        best interests to deauth on their end as well.
        
  GET /event
  
      Paramaters: sessionKey (see auth), timeStart and timeEnd (if filtering by time is desired), 
          Filter paramaters used by COWS rss feeds (see below)
      Returns: Event Information
      Authentication?: If site is not in "anonymous mode"
      Description: Returns a json object representing all events that fit the given filters
  POST /event
  
      Paramaters: sessionKey, Cows Event Paramaters (see below)
      Returns: Nothing
      Authentication?: Yes
      Description: Creates an event with the specified parameters
      
  GET /event/:id
  
      Paramaters: sessionKey (see auth)
      Returns: Event Information
      Authentication?: If site is not in "anonymous mode"
      Description: Returns a json object representing the event with the specified ID
  DELETE /event/:id
  
      Paramaters: sessionkey (via GET or POST due to limitations in DELETE)
      Returns: Nothing
      Authentication?: Yes
      Description: Deletes the event with the specified id

Cows Event Parameters:

Known Cows RSS Filters:
        
  
